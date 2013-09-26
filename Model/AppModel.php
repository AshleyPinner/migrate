<?php
class AppModel extends Model {

	public $useTable = false;

/**
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
	public function source($source = null) {
		if ($source) {
			Configure::write('LH.source', 'data/' . rtrim($source, '/') . '/');
		}

		$return = Configure::read('LH.source');
		if (!$return) {
			return $this->source('export');
		}
		return $return;
	}

	public function project($input = null) {
		if ($input) {
			if ($this->name === 'LHProject') {
				list($account, $project) = $this->id($input);
			} else {
				list($account, $project) = $this->Project->id($input);
			}

			Configure::write('LH.account', $account);
			Configure::write('LH.project', $project);
		}

		$account = Configure::read('LH.account');
		$project = Configure::read('LH.project');
		if (!$account || !$project) {
			throw new CakeException('Account or project hasn\'t been set up yet');
		}

		return [$account, $project];
	}

}
