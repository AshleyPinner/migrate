<?php
App::uses('AppTask', 'Console/Command/Task');

class ReviewTask extends AppTask {

	public $tasks = ['Lighthouse.LH'];

	public $settings = [
		'accept' => null,
		'open' => true,
		'closed' => false
	];

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->description('Interactively review milestones, pages and tickets');
		return $parser;
	}

	public function main($project = null) {
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
		$this->milestones($project);
		$this->pages($project);
		$this->tickets($project);
	}

	public function milestones($project) {
		$settings = $this->settings;

		$milestones = $this->LH->milestones($project);
		foreach ($milestones as $milestone) {
			$this->milestone($project, $milestone);
		}

		$this->settings = $settings;
	}

	public function pages($project) {
		$settings = $this->settings;

		$pages = $this->LH->pages($project);
		foreach ($pages as $page) {
			$this->page($project, $page);
		}

		$this->settings = $settings;
	}

	public function tickets($project) {
		$settings = $this->settings;

		$tickets = $this->LH->tickets($project);
		foreach ($tickets as $ticket) {
			$this->ticket($project, $ticket);
		}

		$this->settings = $settings;
	}

	public function milestone($project, $id) {
		$data = $this->LH->milestone($project, $id);
		$this->_process($project, 'milestones', $id, $data);
	}

	public function page($project, $id) {
		$data = $this->LH->page($project, $id);
		$this->_process($project, 'pages', $id, $data);
	}

	public function ticket($project, $id) {
		$type = 'tickets';
		list($account, $project) = $this->LH->projectId($project);
		$this->_process($project, $type, $id, $data);
	}

	protected function _process($project, $type, $id, $data) {
		list($account, $project) = $this->LH->projectId($project);
		$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$path = $this->_path($account, $project, $type, $id);
		$isSkipped = $this->_isSkipped($path);
		$isAccepted = $this->_isAccepted($path);

		if ($isSkipped || $isAccepted) {
			$what = $isAccepted ? 'accepted' : 'skipped';
			$this->out("Skipping $path, already $what", 1, Shell::VERBOSE);
			return;
		}

		$this->out('Data:');
		$this->out($data);
		$this->hr();

		$accept = $this->settings['accept'];

		if (!$accept) {
			$accept = $this->in(sprintf('Accept this %s?', Inflector::singularize($type)), ['y', 'n', 's', 'Y', 'N', 'S']);

			if ($accept === strtoupper($accept)) {
				$this->settings['accept'] = strtolower($accept);
			}
		}

		if (strtolower($accept) === 's') {
			return;
		}

		if (strtolower($accept) === 'y') {
			return $this->_accept($account, $project, $type, $id, $data);
		}

		return $this->_skip($account, $project, $type, $id, $data);
	}

	protected function _accept($account, $project, $type, $id, $data) {
		$path = $this->_path($account, $project, $type, $id);

		$File = new File('accepted/' . $path, true);
		if (!is_string($data)) {
			$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		return $File->write($data);
	}

	protected function _skip($account, $project, $type, $id, $data) {
		$path = $this->_path($account, $project, $type, $id);

		$File = new File('skipped/' . $path, true);
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
