<?php
App::uses('Shell', 'Console/Command');

class RenumberTask extends Shell {

	public $tasks = ['Lighthouse'];

	public function main($project = null) {
		if (!$project) {
			$projects = $this->Lighthouse->projects();
			foreach ($projects as $project) {
				$this->main($project);
			}
			return;
		}

		$this->_renumber($project);
	}

	protected function _linkCommon($source, $target) {
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
 * _link
 *
 * Silence nonsense (?) file does not exist errors
 *
 * @param mixed $from
 * @param mixed $to
 * @return bool
 */
	protected function _link($from, $to) {
		return @symlink($from, $to);
	}

	protected function _renumber($project) {
		list($account, $project) = $this->Lighthouse->projectId($project);
		if (!$project) {
			return;
		}
		$this->out(sprintf('<info>Processing %s/%s</info>', $account, $project));

		$source = 'export/' . $account . '/projects/' . $project;
		$fromDir = $source . '/tickets';

		$target = 'renumbered/' . $account . '/projects/' . $project;
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

	protected function _renumberTicket($ticketId, $fromDir, $toDir) {
		list($id, $slug) = sscanf($ticketId, '%d-%s');
		$to = str_pad($id, 6, '0', STR_PAD_LEFT) . '-' . $slug;

		$target = $toDir . '/' . $to;
		if (file_exists($target)) {
			$this->out(sprintf(' * Skipping %s, already processed', $ticketId));
			return false;
		}

		$project = basename(dirname(dirname($target)));

		$this->out(sprintf(' * Creating %s/%s', $project, basename($target)), 1);
		return $this->_link(preg_replace('@[^/]+@', '..', $toDir) . '/' . $fromDir . '/' . $ticketId, $target);
	}
}
