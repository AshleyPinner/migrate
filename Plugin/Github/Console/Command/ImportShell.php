<?php
App::uses('Folder', 'Utility');

class ImportShell extends AppShell {

/**
 * Current account
 *
 * @var string
 */
	protected $_account;

/**
 * Current project
 *
 * @var string
 */
	protected $_project;

	protected $_config = [];

	protected $_path = 'accepted/';

	protected $_labels = [];

	protected $_milestones = [];

	protected $_users = [];

	public $tasks = [
		'Lighthouse.LH',
	];

	public function getOptionParser() {
		$parser = new ConsoleOptionParser('Github.import');
		$parser
			->description('Import tickets to github')
			->addArgument('project', array(
				'help' => 'Project name',
				'required' => true
			))
			->epilog('Milestones and labels are created as required, this command requires an api token with commit rights to the relevant repository to be able to create milestones and labels, otherwise tickets are created without this information.');

		return $parser;
	}

	public function main() {
		$this->LH->source('accepted');

		Configure::load('github');
		$this->_config = Configure::read('Github');
		$this->_users = $this->_config['users'];

		$this->Client = new Github\Client();
		$this->Client->authenticate($this->_config['token'], '', Github\Client::AUTH_HTTP_TOKEN);

		if (!$this->args) {
			$this->args = $this->LH->projects();
		}

		foreach ($this->args as $project) {
			$this->import($project);
		}
	}

	public function import($project) {
		list($account, $project) = $this->LH->projectId($this->args[0]);

		$this->_config = Configure::read("Github.projects.$account.$project");
		if (!$this->_config) {
			return false;
		}

		$pid = "$account/$project";
		$tickets = $this->LH->tickets($pid);
		foreach ($tickets as $id) {
			$data = $this->LH->ticket($pid, $id);
			if (!$data) {
				$this->out(sprintf('<error>Skipping invalid ticket with id %s</error>', $id));
				continue;
			}
			if (empty($data['github'])) {
				$data = $this->_createTicket($data);
				$this->LH->updateTicket($pid, $data);
			}

			$this->_createComments($pid, $data);
		}
	}

	protected function _createTicket($data) {
		$ticket = $data['ticket'];
		$toCreate = [
			'title' => $ticket['title'],
			'body' => $ticket['body'],
			'milestone' => $ticket['milestone'],
			'labels' => explode(' ', $ticket['tag'])
		];

		$toCreate = $this->_prepareTicketBody($toCreate, $ticket);
		$toCreate = $this->_translateMilestone($toCreate);
		$toCreate = $this->_ensureLabels($toCreate);

		$issue = $this->Client->api('issue')
			->create($this->_config['account'], $this->_config['project'], $toCreate);

		$this->out(
			sprintf('Github issue %s/%s #%d created for ticket %s', $this->_config['account'], $this->_config['project'], $issue['number'], $ticket['title'])
		);

		$data['github'] = $issue;

		return $data;
	}

	protected function _createComments($pid, $ticket) {
		$return = false;

		foreach ($ticket['comments'] as &$comment) {
			$return = $this->_createComment($comment, $ticket) || $return;
		}

		if ($return) {
			return $this->LH->updateTicket($pid, $ticket);
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
		if (!empty($this->_users[$author])) {
			$author = sprintf('[%s](https://github.com/%s)', $author, $this->_users[$author]);
		}

		$data['body'] = sprintf("Created by **%s**\n", $author) .
			sprintf("On <time datetime='%s'>%s</time>\n", $comment['created_at'], date('jS M Y', strtotime($comment['created_at']))) .
			"- - - -\n" .
			"\n" .
			$data['body'];

		$return = $this->Client->api('issue')->comments()
			->create($this->_config['account'], $this->_config['project'], $ticket['github']['number'], $data);

		if ($return) {
			$comment['github'] = $return;
		}
		return $return;
	}

	protected function _prepareTicketBody($data, $ticket) {
		$data['title'] = $this->_escapeMentions($data['title']);
		$data['body'] = $this->_escapeMentions($data['body']);

		$author = $ticket['created_by'];
		if (!empty($this->_users[$author])) {
			$author = sprintf('[%s](https://github.com/%s)', $author, $this->_users[$author]);
		}
		$data['body'] = sprintf("Created by **%s**\n", $author) .
			sprintf("On <time datetime='%s'>%s</time>\n", $ticket['created_at'], date('jS M Y', strtotime($ticket['created_at']))) .
			sprintf("*(via [Lighthouse](%s))*\n", $ticket['link']) .
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

		if (!$this->_milestones) {
			$response = $this->Client->api('issue')->milestones()
				->all($this->_config['account'], $this->_config['project']);
			$this->_milestones = Hash::combine($response, '{n}.title', '{n}.number');
		}

		if (!isset($this->_milestones[$milestone])) {
			$toCreate = [
				'title' => $milestone
			];
			$result = $this->Client->api('issue')->milestones()
				->create($this->_config['account'], $this->_config['project'], $toCreate);
			$this->_milestones[$milestone] = $result['number'];
		}

		$data['milestone'] = $this->_milestones[$milestone];

		return $data;
	}

	protected function _ensureLabels($data) {
		$labels = array_filter($data['labels']);
		if (!$labels) {
			unset($data['labels']);
			return $data;
		}

		if (!$this->_labels) {
			$response = $this->Client->api('issue')->labels()
				->all($this->_config['account'], $this->_config['project']);
			$this->_labels = Hash::combine($response, '{n}.name', '{n}');
		}

		foreach ($labels as $i => $label) {
			if (!isset($this->_labels[$label])) {
				$toCreate = [
					'name' => $label
				];
				$result = $this->Client->api('issue')->labels()
					->create($this->_config['account'], $this->_config['project'], $toCreate);

				$this->_labels[$label] = $result;
			}
		}

		return $data;
	}

	protected function _update($account, $project, $type, $data) {
		$id = $data['ticket']['filename'];
		$path = $this->_path($account, $project, $type, $id);

		$File = new File('accepted/' . $path, true);
		if (!is_string($data)) {
			$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		return $File->write($data);
	}
}
