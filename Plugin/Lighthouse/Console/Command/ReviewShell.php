<?php

class ReviewShell extends AppShell {

	public $tasks = ['Lighthouse.LH'];

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

	public function main($project = null) {
		if (!$project) {
			if (!$this->_config && file_exists('Config/lighthouse.php')) {
				Configure::load('lighthouse');
				$this->_config = (array)Configure::read('Lighthouse');
			}
			foreach (['users', 'spammers', 'whitelist'] as $key) {
				if (!isset($this->_config[$key])) {
					$this->_config[$key] = [];
				}
			}

			$settings = $this->settings;
			$this->LH->source('accepted');
			$projects = $this->LH->projects();
			foreach ($projects as $project) {
				$this->settings = $settings;
				$this->main($project);
			}
			return;
		}

		$this->tickets($project);
		$this->comments($project);
	}

	public function comments($project) {
		$settings = $this->settings;

		$tickets = $this->LH->tickets($project);
		foreach ($tickets as $id) {
			$data = $this->LH->ticket($project, $id);

			$this->out(sprintf('Reviewing comments for %s', $data['ticket']['title']));

			$updated = false;
			foreach ($data['comments'] as $i => $comment) {
				if (!$this->comment($project, $comment, $data)) {
					unset ($data['comments'][$i]);
					$data['spam'][$i] = $comment;
					$updated = true;
				}
			}

			if ($updated) {
				$this->out(sprintf('<info>Updating ticket %s %s, removing spam comment(s)</info>', $data['ticket']['id'], $data['ticket']['title']));
				list($account, $pid) = $this->LH->projectId($project);
				$this->_update($account, $pid, 'tickets', $data);
			}
		}

		$this->settings = $settings;
	}

	public function comment($project, $comment, $data) {
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

		$tickets = $this->LH->tickets($project);
		foreach ($tickets as $id) {
			$data = $this->LH->ticket($project, $id);
			$this->ticket($project, $data);
		}

		$this->settings = $settings;
	}

	public function ticket($project, $data) {
		list($account, $project) = $this->LH->projectId($project);

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

		$this->out('<warning>Ticket moved to spam</warning>', 1, $verbosity);
		return $this->_skip($account, $project, 'tickets', $data);
	}

	protected function _skip($account, $project, $type, $data) {
		$id = $data['ticket']['filename'];
		$path = $this->_path($account, $project, $type, $id);

		if (file_exists('accepted/' . $path)) {
			unlink('accepted/' . $path);
			rmdir('accepted/' . dirname($path));
		}

		$File = new File('spam/' . $path, true);
		if (!is_string($data)) {
			$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		return $File->write($data);
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

	protected function _path($account, $project, $type, $id) {
		if ($type === 'tickets') {
			$id .= '/ticket.json';
		}
		return "$account/projects/$project/$type/$id";
	}

	protected function _dump($name, array $data) {
		$filename = strtolower($name);
		$name = ucfirst($filename);

		if ($data === Configure::read($name)) {
			$this->out(sprintf('No changes made to %s config', $name), 1, Shell::VERBOSE);
			return false;
		}
		Configure::write($name, $data);

		$this->out(sprintf('<info>Updating %s config</info>', $name, $filename), 1, Shell::VERBOSE);

		$string = "<?php\n\n\$config = ['$name' => " . var_export($data, true) . "];\n";

		$File = new File("Config/$filename.php", true);
		$File->write($string);

		return $name;
	}
}
