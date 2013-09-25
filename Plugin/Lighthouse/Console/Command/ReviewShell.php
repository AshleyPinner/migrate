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
	}

	public function tickets($project) {
		$settings = $this->settings;

		$tickets = $this->LH->tickets($project);
		foreach ($tickets as $id) {
			$data = $this->LH->ticket($project, $id);
			$this->ticket($project, $data);

			$this->_dump('lighthouse', $this->_config);
		}

		$this->settings = $settings;
	}

	public function ticket($project, $data) {
		list($account, $project) = $this->LH->projectId($project);

		$creator = $data['ticket']['created_by'];

		$this->hr();
		$this->out(sprintf('Ticket %s: %s', $data['ticket']['id'], $data['ticket']['title']));
		$this->out($data['ticket']['link'], 2);
		$this->out(String::truncate($data['ticket']['body'], 800), 2);

		$accept = false;

		if (in_array($creator, $this->_config['whitelist'])) {
			$accept = 'y';
			$this->out('<info>Ticket auto-accepted</info>');
		}
		if (in_array($creator, $this->_config['spammers'])) {
			$accept = 'n';
			$this->out('<info>Ticket auto-skipped</info>');
		}

		if (!$accept) {
			$accept = $this->in(sprintf('Approve this ticket by %s?', $creator), ['y', 'n', 'Y', 'N']);

			if ($accept === strtoupper($accept)) {
				if ($accept === 'Y') {
					$this->_config['whitelist'][] = $creator;
				} else {
					$this->_config['spammers'][] = $creator;
					unset($this->_config['users'][$creator]);
				}
			}
		}

		if (strtolower($accept) === 'y') {
			return;
		}

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
		$id = $data['filename'];
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

		$this->out(sprintf('<info>Updating %s config</info>', $name, $filename), 1, Shell::VERBOSE);

		$string = "<?php\n\n\$config = ['$name' => " . var_export($data, true) . "];\n";

		$File = new File("Config/$filename.php", true);
		$File->write($string);

		return $name;
	}
}
