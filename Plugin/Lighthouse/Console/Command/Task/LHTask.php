<?php

App::uses('AppTask', 'Console/Command/Task');
App::uses('Folder', 'Utility');

class LHTask extends Shell {

	protected $_source = 'export/';

	public function load($sourceGz) {
		$file = basename($sourceGz);
		$targetGz = $this->_source . $file;

		mkdir(dirname($targetGz), 0777, true);
		copy($sourceGz, $targetGz);
		passthru(sprintf("cd %s; tar xvzf %s", escapeshellarg($this->_source), escapeShellarg($file)));
		unlink($targetGz);
	}

	public function projectId($input, $account = null, $warn = true) {
		if (file_exists($input)) {
			debug(Debugger::trace());
			debug ($input);
			die;
		}
		if (strpos($input, '/')) {
			list($account, $input) = explode('/', $input);
		}

		if (!$account) {
			$Folder = new Folder($this->_source . $account);
			list($folders) = $Folder->read();
			foreach ($folders as $account) {
				$return = $this->projectId($input, $account, false);
				if (array_filter($return)) {
					return $return;
				}
			}
		} else {
			$Folder = new Folder($this->_source . $account . '/projects');
			list($folders) = $Folder->read();

			$len = strlen($input);
			foreach ($folders as $project) {
				if (
					$project === $input ||
					substr($project, 0, $len) === $input ||
					substr($project, -$len) === $input
				) {
					return array($account, $project);
				}
			}
		}

		if ($warn) {
			$this->err(sprintf('Could not find the project %s %s', $account, $input));
		}
		return array(false, false);
	}

	public function accounts() {
		$Folder = new Folder($this->_source);
		list($accounts) = $Folder->read();
		return $accounts;
	}

	public function projects($account = '*') {
		if ($account === '*') {
			$return = array();
			foreach ($this->accounts() as $account) {

				$projects = $this->projects($account);
				foreach ($projects as &$project) {
					$project = $account . '/' . $project;
				}

				$return = array_merge($return, $projects);
			}

			return $return;
		}

		$Folder = new Folder($this->_source . $account . '/projects');
		list($projects) = $Folder->read();
		return $projects;
	}

	public function config($project) {
		list($account, $project) = $this->projectId($project);

		$path = $this->_source . $account . '/projects/' . $project . '/project.json';

		$config = current($this->_read($path));

		$config['open_states_list'] = explode(',', $config['open_states_list']);
		$config['closed_states_list'] = explode(',', $config['closed_states_list']);

		$keep = [
			'id',
			'name',
			'closed_states_list',
			'open_states_list',
			'open_tickets_count',
			'created_at',
			'updated_at'
		];
		return array_intersect_key($config, array_flip($keep));
	}

	public function tickets($project) {
		list($account, $project) = $this->projectId($project);

		$Folder = new Folder($this->_source . $account . '/projects/' . $project . '/tickets');
		list($tickets) = $Folder->read();
		return $tickets;
	}

	public function pages($project) {
		list($account, $project) = $this->projectId($project);

		$Folder = new Folder($this->_source . $account . '/projects/' . $project . '/pages');
		list(, $pages) = $Folder->read();
		return $pages;
	}

	public function milestones($project) {
		list($account, $project) = $this->projectId($project);

		$path = $this->_source . $account . '/projects/' . $project . '/milestones';

		$Folder = new Folder($path);

		list(, $milestones) = $Folder->read();
		return $milestones;
	}

	public function milestone($project, $id) {
		list($account, $project) = $this->projectId($project);
		$path = $this->_source . $account . '/projects/' . $project . '/milestones/' . $id;

		return current($this->_read($path));
	}

	public function page($project, $id) {
		list($account, $project) = $this->projectId($project);
		$path = $this->_source . $account . '/projects/' . $project . '/pages/' . $id;

		return current($this->_read($path));
	}

	public function ticket($project, $id) {
		list($account, $project) = $this->projectId($project);
		$path = $this->_source . $account . '/projects/' . $project . '/tickets/' . $id . '/ticket.json';

		$return = $this->_read($path);

		if ($this->_source !== 'accepted/') {
			$return = current($return);
		}
		return $return;
	}

	public function source($source = null) {
		if ($source) {
			$this->_source = rtrim($source, '/') . '/';
		}
		return $this->_source;
	}

	public function updateTicket($project, $data) {
		list($account, $project) = $this->projectId($project);
		$this->_write($account, $project, 'tickets', $data['ticket']['filename'], $data);
	}

/**
 * _read
 *
 * @throws CakeException if the file doesn't exist
 * @param mixed $path
 * @return void
 */
	protected function _read($path) {
		if (!file_exists($path)) {
			throw new CakeException(sprintf('The file %s doesn\'t exist', $path));
		}
		return json_decode(file_get_contents($path), true);
	}

	protected function _write($account, $project, $type, $id, $data) {
		$path = $this->_path($account, $project, $type, $id);

		$path = $this->_source . $path;
		$this->out("Updating $path", 1, Shell::VERBOSE);

		$File = new File($path, true);

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

}
