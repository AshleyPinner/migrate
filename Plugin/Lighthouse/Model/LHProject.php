<?php

App::uses('Folder', 'Utility');
App::uses('LighthouseAppModel', 'Lighthouse.Model');

class LHProject extends LighthouseAppModel {

/**
 * projectId
 *
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
				$return = $this->projectId($input, $account, false);
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
 * load
 *
 * @param mixed $sourceGz
 * @return void
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
 * renumber (tickets) for a project
 *
 * @return void
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
 * create a symlink from one file/folder to another location
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
	protected function _link($from, $to) {
		if (file_exists($to)) {
			$this->log(sprintf('skipping %s, already exists', $to), LOG_DEBUG);
			return false;
		}

		$this->log(sprintf('linking %s to %s', $to, $from), LOG_INFO);
		return symlink($from, $to);
	}
}
