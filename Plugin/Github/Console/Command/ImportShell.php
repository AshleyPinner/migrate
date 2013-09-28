<?php
App::uses('Folder', 'Utility');

class ImportShell extends AppShell {

	protected $_client;

	protected $_config = [];

	protected $_projectConfig = [];

	public $uses = [
		'Lighthouse.LHProject',
		'Lighthouse.LHTicket',
	];

	public function getOptionParser() {
		$parser = new ConsoleOptionParser('Github.import');
		$parser
			->description('Import tickets to github')
			->addArgument('project', [
				'help' => 'Project name',
				'required' => true
			])
			->epilog('Milestones and labels are created as required, this command requires an api token with commit rights to the relevant repository to be able to create milestones and labels, otherwise tickets are created without this information.');

		return $parser;
	}

	public function client($client = null) {
		if ($client) {
			$this->_client = $client;
		}

		if (empty($this->_client)) {
			$this->_client = new Github\Client();
			$this->_client
				->authenticate($this->_config['token'], '', Github\Client::AUTH_HTTP_TOKEN);
		}

		return $this->_client;
	}

	public function main() {
		$this->LHProject->source('accepted');

		Configure::load('github');
		$this->_config = Configure::read('Github');

		$projects = $this->args;
		if (!$projects) {
			$projects = $this->LHProject->all();
		}

		foreach ($projects as $project) {
			$this->import($project);
		}
	}

	public function import($project) {
		list($account, $project) = $this->LHProject->project($project);

		if (empty($this->_config['projects'][$account][$project])) {
			return false;
		}

		$this->_projectConfig =& $this->_config['projects'][$account][$project];
		if (
			!$this->_projectConfig['account'] ||
			!$this->_projectConfig['project']
		) {
			return false;
		}

		if (empty($this->_projectConfig['labels'])) {
			$this->_projectConfig['labels'] = [];
		}
		if (empty($this->_projectConfig['milestones'])) {
			$this->_projectConfig['milestones'] = [];
		}

		foreach ($this->LHTicket->all() as $id) {
			$data = $this->LHTicket->data($id);
			if (!$data) {
				$this->out(sprintf('<error>Skipping invalid ticket with id %s</error>', $id));
				continue;
			}

			if (empty($data['github'])) {
				$data = $this->_createTicket($id, $data);
				if (!$data) {
					continue;
				}
			}

			$this->_createComments($id, $data);
		}
	}

	protected function _createTicket($id, $data) {
		$ticket = $data['ticket'];
		$toCreate = [
			'title' => $ticket['title'],
			'body' => $ticket['body'],
			'milestone' => $ticket['milestone'],
			'labels' => $this->_deriveTags($ticket['tag'])
		];

		$toCreate = $this->_prepareTicketBody($toCreate, $ticket);
		$toCreate = $this->_translateMilestone($toCreate);
		$toCreate = $this->_ensureLabels($toCreate);

		$issue = $this->client()->api('issue')
			->create($this->_projectConfig['account'], $this->_projectConfig['project'], $toCreate);

		$this->out(
			sprintf('Github issue %s/%s #%d created for ticket %s', $this->_projectConfig['account'], $this->_projectConfig['project'], $issue['number'], $ticket['title'])
		);

		$data['github'] = $issue;
		$this->LHTicket->update($id, $data);

		return $data;
	}

	protected function _createComments($id, $data) {
		$updated = false;

		foreach ($data['comments'] as &$comment) {
			$updated = $this->_createComment($comment, $data) || $updated;
		}

		if ($updated) {
			return $this->LHTicket->update($id, $data);
		}
		return false;
	}

	protected function _createComment(&$comment, $ticket) {
		if (!empty($comment['github']) ||
			!$comment['body'] ||
			$ticket['ticket']['body'] === $comment['body']) {
			return false;
		};

		$data['body'] = $this->_escapeMentions($comment['body']);

		$author = $comment['user_name'];
		if (!empty($this->_config['users'][$author])) {
			$author = sprintf('[%s](https://github.com/%s)', $author, $this->_config['users'][$author]);
		}

		$data['body'] = sprintf("%s, **%s** said:\n", date('jS M Y', strtotime($comment['created_at'])), $author) .
			"- - - -\n" .
			"\n" .
			$data['body'];

		$updated = $this->client()->api('issue')->comments()
			->create($this->_projectConfig['account'], $this->_projectConfig['project'], $ticket['github']['number'], $data);

		if ($updated) {
			$comment['github'] = $updated;
		}
		return $updated;
	}

	protected function _prepareTicketBody($data, $ticket) {
		$data['title'] = $this->_escapeMentions($data['title']);
		$data['body'] = $this->_escapeMentions($data['body']);

		$author = $ticket['created_by'];
		if (!empty($this->_config['users'][$author])) {
			$author = sprintf('[%s](https://github.com/%s)', $author, $this->_config['users'][$author]);
		}
		$data['body'] = sprintf("Created by **%s**, %s. ", $author, date('jS M Y', strtotime($ticket['created_at']))) .
			sprintf("*(originally [Lighthouse ticket #%s](%s))*:\n", $ticket['id'], $ticket['link']) .
			"- - - -\n" .
			"\n" .
			$data['body'];

		return $data;
	}

	protected function _escapeMentions($data) {
		return preg_replace('/@(\w+)/', '`@\1`', $data);
	}

	protected function _translateMilestone($data) {
		$milestone = $data['milestone'];
		if (!$milestone) {
			unset($data['milestone']);
			return $data;
		}

		if (!$this->_projectConfig['milestones']) {
			$response = $this->client()->api('issue')->milestones()
				->all($this->_projectConfig['account'], $this->_projectConfig['project']);
			$this->_projectConfig['milestones'] = Hash::combine($response, '{n}.title', '{n}.number');
			$this->_dump('github', $this->_config);
		}

		if (!isset($this->_projectConfig['milestones'][$milestone])) {
			$toCreate = [
				'title' => $milestone
			];

			try {
				$result = $this->client()->api('issue')->milestones()
					->create($this->_projectConfig['account'], $this->_projectConfig['project'], $toCreate);
			} catch (Github\Exception\ValidationFailedException $e) {
				$message = $e->getMessage();
				if (strpos($message, 'already exists')) {
					// the milestone already exists - handle silently
					$response = $this->client()->api('issue')->milestones()
						->all($this->_projectConfig['account'], $this->_projectConfig['project']);
					$this->_projectConfig['milestones'] = Hash::combine($response, '{n}.title', '{n}.number');
					$this->_dump('github', $this->_config);
					$data['milestone'] = $this->_projectConfig['milestones'][$milestone];

					return $data;
				} else {
					throw $e;
				}
			}
			$this->_projectConfig['milestones'][$milestone] = $result['number'];
			$this->_dump('github', $this->_config);
		}

		$data['milestone'] = $this->_projectConfig['milestones'][$milestone];

		return $data;
	}

	protected function _ensureLabels($data) {
		$labels = array_filter($data['labels']);
		if (!$labels) {
			unset($data['labels']);
			return $data;
		}

		if (!$this->_projectConfig['labels']) {
			$response = $this->client()->api('issue')->labels()
				->all($this->_projectConfig['account'], $this->_projectConfig['project']);
			$this->_projectConfig['labels'] = Hash::combine($response, '{n}.name', '{n}.name');
			$this->_dump('github', $this->_config);
		}

		foreach ($labels as $i => $label) {
			if (!isset($this->_projectConfig['labels'][$label])) {
				$toCreate = [
					'name' => $label
				];

				try {
					$result = $this->client()->api('issue')->labels()
						->create($this->_projectConfig['account'], $this->_projectConfig['project'], $toCreate);
				} catch (Github\Exception\ValidationFailedException $e) {
					$message = $e->getMessage();
					if (strpos($message, 'already exists')) {
						// the label already exists - handle silently
					} else {
						throw $e;
					}
				}

				$this->_projectConfig['labels'][$label] = $label;
				$this->_dump('github', $this->_config);
			}
		}

		return $data;
	}

/**
 * _deriveTags
 *
 * Account for the way lighthouse returns tags as a string of the format:
 *   foo "multi word tag" bar BAR
 *
 * And permitting duplicate tags that differ only by case
 *
 * @param string $input
 * @return array
 */
	protected function _deriveTags($input) {
		$tags = [];

		if (preg_match_all('@"(.+)"@', $input, $matches)) {
			$tags = $matches[1];
			$input = str_replace($matches[0], '', $input);
		}

		$tags = array_merge(
			$matches[1],
			array_filter(explode(' ', $input))
		);
		$tags = array_unique(array_map('strtolower', $tags));
		sort($tags);

		return $tags;
	}
}
