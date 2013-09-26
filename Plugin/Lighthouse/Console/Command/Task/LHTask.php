<?php

App::uses('Folder', 'Utility');

class LHTask extends Shell {

/**
 * _source
 *
 * Where to read data from, there are several folders used by the shell:
 *
 * - export
 * - renumbered
 * - accepted
 * - skipped
 * - spam
 *
 * @var string
 */
	protected $_source = 'export/';

/**
 * load a Lighthouse account dump file
 *
 * An account dump file is just a gzipped tar - extract it where we want to use it.
 *
 * @param string $sourceGz
 * @return void
 */
	public function load($sourceGz) {
		$file = basename($sourceGz);
		$targetGz = $this->_source . $file;

		mkdir(dirname($targetGz), 0777, true);
		copy($sourceGz, $targetGz);
		passthru(sprintf("cd %s; tar xvzf %s", escapeshellarg($this->_source), escapeShellarg($file)));
		unlink($targetGz);
	}

/**
 * projectId
 *
 * Determine the project id from (user) input, for the project user/12345-project-name,
 * Will accept any of:
 *
 *  - path/to/type/user/projects/12345-project-name
 *  - user/12345-project-name
 *  - user/12345
 *  - user/project-name
 *  - 12345
 *  - project-name
 *
 * Returning:
 *
 * ['user', '12345-project-name']
 *
 * @param string $input
 * @param string $account
 * @param bool $warn
 * @return array
 */
	public function projectId($input, $account = null, $warn = true) {
		if (file_exists($input)) {
			preg_match('@([^/]*)/projects/([^/]*)@', $input, $match);
			if ($match) {
				$account = $match[1];
				$project = $match[2];
			}
			return [$account, $project];
		}

		if (strpos($input, '/')) {
			list($account, $input) = explode('/', $input);
			return [$account, $project];
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

/**
 * list all accounts
 *
 * @return array
 */
	public function accounts() {
		$Folder = new Folder($this->_source);
		list($accounts) = $Folder->read();
		return $accounts;
	}

/**
 * List all projects
 *
 * If an account is specified, only projects in that account are returned, otherwise
 * all accounts are returned. the format for the return value is:
 *
 * [
 *   'account/12345-project',
 *   'another-account/456789-another-project'
 * ]
 *
 * @param string $account
 * @return array
 */
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

/**
 * The config for a given project, as provided by lighthouse
 *
 * @param string $project
 * @return array
 */
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

/**
 * all ticket (ids) for a given project
 *
 * @param string $project
 * @return array
 */
	public function tickets($project) {
		list($account, $project) = $this->projectId($project);

		$Folder = new Folder($this->_source . $account . '/projects/' . $project . '/tickets');
		list($tickets) = $Folder->read();
		return $tickets;
	}

/**
 * all page (ids) for a given project
 *
 * @param string $project
 * @return array
 */
	public function pages($project) {
		list($account, $project) = $this->projectId($project);

		$Folder = new Folder($this->_source . $account . '/projects/' . $project . '/pages');
		list(, $pages) = $Folder->read();
		return $pages;
	}

/**
 * all milestones for a given project
 *
 * @param string $project
 * @return array
 */
	public function milestones($project) {
		list($account, $project) = $this->projectId($project);

		$path = $this->_source . $account . '/projects/' . $project . '/milestones';

		$Folder = new Folder($path);

		list(, $milestones) = $Folder->read();
		return $milestones;
	}

/**
 * Data for a single milestone
 *
 * @param string $project
 * @param string $id
 * @return array
 */
	public function milestone($project, $id) {
		list($account, $project) = $this->projectId($project);
		$path = $this->_source . $account . '/projects/' . $project . '/milestones/' . $id;

		return current($this->_read($path));
	}

/**
 * Data for a single page
 *
 * @param string $project
 * @param string $id
 * @return array
 */
	public function page($project, $id) {
		list($account, $project) = $this->projectId($project);
		$path = $this->_source . $account . '/projects/' . $project . '/pages/' . $id;

		return current($this->_read($path));
	}

/**
 * Data for a single ticket
 *
 * @param string $project
 * @param string $id
 * @return array
 */
	public function ticket($project, $id) {
		list($account, $project) = $this->projectId($project);
		$path = $this->_source . $account . '/projects/' . $project . '/tickets/' . $id . '/ticket.json';

		$return = $this->_read($path);

		if ($this->_source !== 'accepted/') {
			$return = current($return);
		}
		return $return;
	}

/**
 * set or get the source for lighthouse data
 *
 * @param string $source
 * @return string
 */
	public function source($source = null) {
		if ($source) {
			$this->_source = rtrim($source, '/') . '/';
		}
		return $this->_source;
	}

/**
 * Update the stored ticket data
 *
 * @param string $project
 * @param array $data
 * @return void
 */
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

/**
 * Get the path to a json file of a specific type
 *
 * LH export files store files in the following format:
 *
 * account/
 *   projects/
 *     9999-project/
 *       milestones/
 *         9999-name.json
 *       pages/
 *         name.json
 *       tickets/
 *         9999-name/ticket.json
 *
 * @param string $account
 * @param string $project
 * @param string $type
 * @param string $id
 * @return string
 */
	protected function _path($account, $project, $type, $id) {
		if ($type === 'tickets') {
			$id .= '/ticket.json';
		}
		return "$account/projects/$project/$type/$id";
	}

}
