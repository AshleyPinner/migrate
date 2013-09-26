<?php

App::uses('Folder', 'Utility');
App::uses('LighthouseAppModel', 'Lighthouse.Model');

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
class LHProject extends LighthouseAppModel {

	public function id($input, $account = null, $warn = true) {
		if (preg_match('@([^/]*)/projects/([^/]*)@', $input, $match)) {
			$account = $match[1];
			$project = $match[2];
			return [$account, $project];
		}

		if (strpos($input, '/')) {
			list($account, $input) = explode('/', $input);
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

		if ($warn) {
			$this->log(sprintf('Could not find the project %s %s', $account, $input));
		}
		return array(false, false);
	}

	public function load($sourceGz) {
		$file = basename($sourceGz);
		$targetGz = $this->source() . $file;

		mkdir(dirname($targetGz), 0777, true);
		copy($sourceGz, $targetGz);
		passthru(sprintf("cd %s; tar xvzf %s", escapeshellarg($this->source()), escapeShellarg($file)));
		unlink($targetGz);

	}
}
