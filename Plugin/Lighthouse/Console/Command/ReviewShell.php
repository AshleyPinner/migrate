<?php

class ReviewShell extends AppShell {

	public $uses = [
		'Lighthouse.LHProject',
		'Lighthouse.LHTicket'
	];

	public $settings = [
		'accept' => null,
		'open' => true,
		'closed' => false
	];

	protected $_config = [];

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description('Interactively review approved tickets')
			->epilog('Lighthouse tickets can be rife with spam, use this shell to review tickets and unapprove spam before the data is migrated to the target system');

		return $parser;
	}

	public function main() {
		if (!$this->_config && file_exists('Config/lighthouse.php')) {
			Configure::load('lighthouse');
			$this->_config = (array)Configure::read('Lighthouse');
		}
		foreach (['users', 'spammers', 'whitelist'] as $key) {
			if (!isset($this->_config[$key])) {
				$this->_config[$key] = [];
			}
		}

		$this->LHProject->source('accepted');
		$projects = $this->args;
		if (!$projects) {
			$projects = $this->LHProject->all();
		}

		$settings = $this->settings;
		foreach ($projects as $project) {
			$this->settings = $settings;
			$this->project($project);
		}
	}

	public function project($project) {
		$this->tickets($project);
		$this->comments($project);
	}

	public function comments($project) {
		$settings = $this->settings;

		$tickets = $this->LHTicket->all($project);
		foreach ($tickets as $id) {
			$data = $this->LHTicket->data($id);
			if (!$data['comments']) {
				$this->out(sprintf('<info>No comments for %s</info>', $data['ticket']['title']), Shell::VERBOSE);
				continue;
			}

			$this->out(sprintf('Reviewing comments for %s', $data['ticket']['title']));

			$updated = false;
			foreach ($data['comments'] as $i => $comment) {
				if (!$this->comment($id, $comment, $data)) {
					unset ($data['comments'][$i]);
					$data['spam'][$i] = $comment;
					$updated = true;
				}
			}

			if ($updated) {
				$this->out(sprintf('<info>Updating ticket %s %s, removing spam comment(s)</info>', $data['ticket']['id'], $data['ticket']['title']));
				$this->LHTicket->update($id, $data);
			}
		}

		$this->settings = $settings;
	}

	public function comment($idt, $comment, $data) {
		$creator = $comment['user_name'];

		$accept = false;
		$verbosity = Shell::NORMAL;
		if (in_array($creator, $this->_config['whitelist'])) {
			$accept = 'y';
			$verbosity = Shell::VERBOSE;
		}
		if (in_array($creator, $this->_config['spammers'])) {
			$accept = 'n';
			$verbosity = Shell::VERBOSE;
		}

		if ($verbosity === Shell::NORMAL || !empty($this->params['verbose'])) {
			$this->clear();
		}
		$this->out(sprintf('Comment on ticket %s: %s', $data['ticket']['id'], $data['ticket']['title']), 1, $verbosity);
		$this->out($data['ticket']['link'], 2, $verbosity);
		$this->out(String::truncate($comment['body'], 800), 2, $verbosity);

		if (!$accept) {
			$accept = $this->in(sprintf('Approve this comment by %s?', $creator), ['y', 'n', 'Y', 'N']);

			if ($accept === strtoupper($accept)) {
				if ($accept === 'Y') {
					$this->_config['whitelist'][] = $creator;
				} else {
					$this->_config['spammers'][] = $creator;
					unset($this->_config['users'][$creator]);
				}

				$this->_dump('lighthouse', $this->_config);
			}
		}

		if (strtolower($accept) === 'y') {
			$this->out('<info>Comment accepted</info>', 1, $verbosity);
			return true;
		}

		$this->out('<warning>Comment rejected</warning>', 1, $verbosity);
		return false;
	}

	public function tickets($project) {
		$settings = $this->settings;

		$tickets = $this->LHTicket->all($project);
		foreach ($tickets as $id) {
			$this->ticket($id);
		}

		$this->settings = $settings;
	}

	public function ticket($id) {
		$data = $this->LHTicket->data($id);

		$creator = $data['ticket']['created_by'];

		$accept = false;
		$verbosity = Shell::NORMAL;
		if (in_array($creator, $this->_config['whitelist'])) {
			$accept = 'y';
			$verbosity = Shell::VERBOSE;
		}
		if (in_array($creator, $this->_config['spammers'])) {
			$accept = 'n';
			$verbosity = Shell::VERBOSE;
		}

		if ($verbosity === Shell::NORMAL || !empty($this->params['verbose'])) {
			$this->clear();
		}
		$this->out(sprintf('Ticket %s: %s', $data['ticket']['id'], $data['ticket']['title']), 1, $verbosity);
		$this->out($data['ticket']['link'], 2, $verbosity);
		$this->out(String::truncate($data['ticket']['body'], 800), 2, $verbosity);

		if (!$accept) {
			$accept = $this->in(sprintf('Approve this ticket by %s?', $creator), ['y', 'n', 'Y', 'N']);

			if ($accept === strtoupper($accept)) {
				if ($accept === 'Y') {
					$this->_config['whitelist'][] = $creator;
				} else {
					$this->_config['spammers'][] = $creator;
					unset($this->_config['users'][$creator]);
				}

				$this->_dump('lighthouse', $this->_config);
			}
		}

		if (strtolower($accept) === 'y') {
			$this->out('<info>Ticket accepted</info>', 1, $verbosity);
			return;
		}
		$this->_markTicketAsSpam($id, $verbosity);
	}

	protected function _markTicketAsSpam($id, $verbosity) {
		$path = $this->LHTicket->path($id, true);
		$spamPath = str_replace('accepted', 'spam', $path);

		$File = new File($spamPath, true);
		$File->copy($path);
		unlink($path);
		rmdir(dirname($path));

		$this->out('<warning>Ticket moved to spam</warning>', 1, $verbosity);
	}

}
