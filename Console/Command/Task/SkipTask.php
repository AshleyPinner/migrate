<?php
App::uses('AppTask', 'Console/Command/Task');

class SkipTask extends AppTask {

	public $tasks = ['LH'];

	public $settings = [
		'open' => false,
		'closed' => false
	];

	protected $_pathPrefix = 'skipped/';

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description('Skip tickets by state')
			->addOption('open', array(
				'boolean' => true,
				'help' => 'All open tickets'
			))
			->addOption('closed', array(
				'boolean' => true,
				'help' => 'All closed tickets'
			));

		return $parser;
	}

	public function main($project = null) {
		if (empty($this->settings['open']) && empty($this->settings['closed'])) {
			return $this->out($this->getOptionParser()->help());
		}

		if (!$project) {
			$settings = $this->settings;
			$this->LH->source('renumbered');
			$projects = $this->LH->projects();
			foreach ($projects as $project) {
				$this->settings = $settings;
				$this->main($project);
			}
			return;
		}

		$this->settings += $this->LH->config($project);
		$this->tickets($project);
	}

	public function tickets($project) {
		$settings = $this->settings;

		$tickets = $this->LH->tickets($project);
		foreach ($tickets as $ticket) {
			$this->ticket($project, $ticket);
		}

		$this->settings = $settings;
	}

	public function ticket($project, $id) {
		$type = 'tickets';
		list($account, $project) = $this->LH->projectId($project);
		$path = $this->_path($account, $project, $type, $id);
		$isSkipped = $this->_isSkipped($path);
		$isAccepted = $this->_isAccepted($path);

		if ($isSkipped || $isAccepted) {
			$what = $isAccepted ? 'accepted' : 'skipped';
			$this->out("Skipping $path, already $what", 1, Shell::VERBOSE);
			return;
		}

		$data = $this->LH->ticket($project, $id);

		$isOpen = $isClosed = false;
		$state = $data['state'];
		if (in_array($state, $this->settings['open_states_list'])) {
			$isOpen = true;
		}
		if (in_array($state, $this->settings['closed_states_list'])) {
			$isClosed = true;
		}

		$accept = null;
		if (
			($this->settings['open'] && $isOpen) ||
			($this->settings['closed'] && $isClosed)
		) {
			$accept = true;
		}

		if ($accept === true) {
			return $this->_write($account, $project, $type, $id, $data);
		}
	}

	protected function _write($account, $project, $type, $id, $data) {
		$path = $this->_path($account, $project, $type, $id);

		$path = $this->_pathPrefix . $path;
		$this->out("Writing $path");

		$File = new File($path, true);
		if (!is_string($data)) {
			$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		return $File->write($data);
	}

	protected function _isSkipped($path) {
		return file_exists('skipped/' . $path);
	}

	protected function _isAccepted($path) {
		return file_exists('accepted/' . $path);
	}

	protected function _path($account, $project, $type, $id) {
		if ($type === 'tickets') {
			$id .= '/ticket.json';
		}
		return "$account/$project/$type/$id";
	}

}
