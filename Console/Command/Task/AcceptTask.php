<?php
App::uses('Shell', 'Console/Command');

class AcceptTask extends Shell {

	public $tasks = ['Lighthouse'];

	public $settings = [
		'accept' => null,
		'open' => true,
		'closed' => false
	];

	protected $_pathPrefix = 'accepted/';

	public function main($project = null) {
		if (!$project) {
			$settings = $this->settings;
			$this->Lighthouse->source('renumbered');
			$projects = $this->Lighthouse->projects();
			foreach ($projects as $project) {
				$this->settings = $settings;
				$this->main($project);
			}
			return;
		}

		$this->settings += $this->Lighthouse->config($project);
		$this->tickets($project);
	}

	public function tickets($project) {
		$settings = $this->settings;

		$tickets = $this->Lighthouse->tickets($project);
		foreach ($tickets as $ticket) {
			$this->ticket($project, $ticket);
		}

		$this->settings = $settings;
	}

	public function ticket($project, $id) {
		$type = 'tickets';
		list($account, $project) = $this->Lighthouse->projectId($project);
		$path = $this->_path($account, $project, $type, $id);
		$isSkipped = $this->_isSkipped($path);
		$isAccepted = $this->_isAccepted($path);

		if ($isSkipped || $isAccepted) {
			$what = $isAccepted ? 'accepted' : 'skipped';
			$this->out("Skipping $path, already $what", 1, Shell::VERBOSE);
			return;
		}

		$data = $this->Lighthouse->ticket($project, $id);

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

		$File = new File($this->_pathPrefix . $path, true);
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
		return "$account/$project/$type/$id";
	}

}
