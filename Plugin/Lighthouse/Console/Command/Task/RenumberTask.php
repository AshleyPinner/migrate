<?php
App::uses('AppTask', 'Console/Command/Task');

class RenumberTask extends AppTask {

	public $tasks = ['Lighthouse.LH'];

	public $settings = [
		'y' => null,
		'n' => null
	];

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description('Rename export files so they are in numerical order')
			->addOption('yes', array(
				'boolean' => true,
				'help' => 'Skip the interactive check, answer yes to all questions'
			))
			->addOption('no', array(
				'boolean' => true,
				'help' => 'Skip the interactive check, answer no to all questions'
			));

		return $parser;
	}

	public function main($project = null) {
		$this->settings['n'] = $this->params['no'];
		$this->settings['y'] = $this->params['yes'];

		if (!$project) {
			$projects = $this->LH->projects();
			foreach ($projects as $project) {
				$this->main($project);
			}
			return;
		}

		$this->_renumber($project);
	}

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
 * _link
 *
 * @param mixed $from
 * @param mixed $to
 * @return bool
 */
	protected function _link($from, $to) {
		if (file_exists($to)) {
			$this->out(sprintf(' * skipping %s, already exists', $to), 1, Shell::VERBOSE);
			return false;
		}

		if ($this->settings['n']) {
			$this->out(sprintf(' * skipping %s, dry run', $to), 1, Shell::VERBOSE);
			return false;
		}

		$yes = $this->settings['y'];
		if (!$yes) {
			$answer = $this->in(sprintf('link %s to %s?', $to, $from), ['y', 'n', 'Y', 'N'], 'n');
			if ($answer === strtoupper($answer)) {
				$answer = strtolower($answer);
				$this->settings[$answer] = true;
			}

			if ($answer === 'n') {
				return false;
			}
		}

		$this->out(sprintf(' * linking %s to %s', $to, $from), 1, Shell::VERBOSE);
		return symlink($from, $to);
	}

	protected function _renumber($project) {
		list($account, $project) = $this->LH->projectId($project);
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
			$this->out(sprintf(' * Skipping %s, already processed', $ticketId), 1, Shell::VERBOSE);
			return false;
		}

		$project = basename(dirname(dirname($target)));

		return $this->_link(preg_replace('@[^/]+@', '..', $toDir) . '/' . $fromDir . '/' . $ticketId, $target);
	}
}
