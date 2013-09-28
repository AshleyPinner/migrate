<?php

App::uses('Folder', 'Utility');
App::uses('LighthouseAppModel', 'Lighthouse.Model');

class LHProject extends LighthouseAppModel {

/**
 * list all projects by id
 *
 * @param string $account
 * @return array
 */
	public function all($account = '*') {
		if ($account === '*') {
			$return = array();

			foreach ($this->accounts() as $account) {
				$projects = $this->all($account);
				$return = array_merge($return, $projects);
			}
			return $return;
		}

		$Folder = new Folder($this->source() . $account . '/projects');

		list($return) = $Folder->read();

		foreach ($return as &$ret) {
			$ret = $account . '/' . $ret;
		}
		return $return;
	}

/**
 * accounts
 *
 * List all accounts - doesn't really belong in the project model.. but source
 * is defined here, and it's only really used by the "all projects" function
 *
 * @return array
 */
	public function accounts() {
		$Folder = new Folder($this->source());
		list($accounts) = $Folder->read();
		return $accounts;
	}

	public function config($project = null) {
		list($account, $project) = $this->project($project);

		$config = $this->data($project);

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
 * Determine the project id from (user) input, for the project user/12345-project-name,
 * Will accept any of:
 *
 *  - path/to/type/account-name/projects/12345-project-name
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
	public function id($input, $account = null) {
		if (preg_match('@([^/]*)/projects/([^/]*)@', $input, $match)) {
			$account = $match[1];
			$project = $match[2];
			return [$account, $project];
		}

		if (strpos($input, '/')) {
			list($account, $project) = explode('/', $input);
			return [$account, $project];
		}

		if (!$account) {
			$Folder = new Folder($this->source() . $account);
			list($folders) = $Folder->read();
			foreach ($folders as $account) {
				$return = $this->id($input, $account, false);
				if (array_filter($return)) {
					return $return;
				}
			}
		} else {
			$Folder = new Folder($this->source() . $account . '/projects');
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

		return false;
	}

/**
 * setId
 *
 * Set the active project
 *
 * @param string $id
 */
	public function setId($id) {
		list($account, $project) = $this->id($id);
		Configure::write('LH.account', $account);
		Configure::write('LH.project', $project);
	}

/**
 * load a lighthouse export file
 *
 * A lighthouse export file is just a gzipped tar ball - expand it into the export folder
 *
 * @param mixed $sourceGz
 */
	public function load($sourceGz) {
		$file = basename($sourceGz);
		$targetGz = $this->source() . $file;

		mkdir(dirname($targetGz), 0777, true);
		copy($sourceGz, $targetGz);
		passthru(sprintf("cd %s; tar xvzf %s", escapeshellarg($this->source()), escapeShellarg($file)));
		unlink($targetGz);
	}

/**
 * Overridden to define the path to a project config file
 *
 * @param string $id
 * @return string
 */
	public function path($id, $full = false) {
		list($account, $project) = $this->project();
		$return = "$account/projects/$project/project.json";

		if ($full) {
			return $this->source() . $return;
		}
	}

/**
 * renumber (tickets) for a project
 *
 * Also links unmodified files so that the renumbered data is a complete copy of the export data
 */
	public function renumber() {
		list($account, $project) = $this->project();

		$this->log(sprintf('Processing %s/%s', $account, $project), LOG_INFO);

		$path = $account . '/projects/' . $project;

		$source = $this->source('export') . $path;
		$fromDir = $source . '/tickets';

		$target = $this->source('renumbered') . $path;
		$toDir = $target . '/tickets';

		if (!is_dir($toDir)) {
			mkdir($toDir, 0777, true);
		}
		$this->_linkCommon($source, $target);

		$Folder = new Folder($fromDir);
		list($tickets) = $Folder->read();

		foreach ($tickets as $id) {
			$this->_renumberTicket($id, $fromDir, $toDir);
		}
	}

/**
 * create a symlink from one file/folder to another location
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
	protected function _link($from, $to) {
		if (file_exists($to)) {
			$this->log(sprintf('skipping %s, already exists', $this->_shortPath($to)), LOG_DEBUG);
			return false;
		}

		$this->log(sprintf('linking %s', $this->_shortPath($to)), LOG_INFO);
		return symlink($from, $to);
	}

/**
 * Link the common files that are in a lighthouse export
 *
 * @param string $source
 * @param string $target
 * @return bool
 */
	protected function _linkCommon($source, $target) {
		if (substr($source, -1) !== '/') {
			$source .= '/';
		}
		if (substr($target, -1) !== '/') {
			$target .= '/';
		}

		$Folder = new Folder($source);
		list($folders, $files) = $Folder->read();
		$all = array_merge($folders, $files);

		$success = true;
		foreach ($all as $node) {
			if ($node === 'tickets') {
				continue;
			}
			$success = $success && $this->_link(preg_replace('@[^/]+@', '..', $target) . $source . $node, $target . $node);
		}
		return $success;
	}

/**
 * _renumberTicket
 *
 * Reformat the ticket to be a six digit, 0-padded number with the original slug
 * Then create a symlink to the original ticket
 *
 * @param string $ticketId
 * @param string $fromDir
 * @param string $toDir
 * @return bool
 */
	protected function _renumberTicket($ticketId, $fromDir, $toDir) {
		list($id, $slug) = sscanf($ticketId, '%d-%s');
		$to = str_pad($id, 6, '0', STR_PAD_LEFT) . '-' . $slug;

		$target = $toDir . '/' . $to;
		$project = basename(dirname(dirname($target)));

		return $this->_link(preg_replace('@[^/]+@', '..', $toDir) . '/' . $fromDir . '/' . $ticketId, $target);
	}

/**
 * Use a shorter path in log messages
 *
 * @param string $path
 * @return string
 */
	protected function _shortPath($path) {
		return preg_replace('@.*/([^/]*)/projects/(.*)@', '\2', $path);
	}
}
