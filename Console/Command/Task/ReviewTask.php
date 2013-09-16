<?php
App::uses('Shell', 'Console/Command');

class ReviewTask extends Shell {

	public $tasks = ['Lighthouse'];

	public $settings = [

	];

	public function main($project = null) {
		if (!$project) {
			$this->Lighthouse->source('renumbered');
			$projects = $this->Lighthouse->projects();
			foreach ($projects as $project) {
				$this->main($project);
			}
			return;
		}

		$this->milestones($project);
		//$this->pages($project);
		//$this->tickets($project);
	}

	public function milestones($project) {
		list($account, $project) = $this->Lighthouse->projectId($project);

		$milestones = $this->Lighthouse->milestones($project);
	}

	public function pages($project) {
		list($account, $project) = $this->Lighthouse->projectId($project);

		$pages = $this->Lighthouse->pages($project);
	}

	public function tickets($project) {
		list($account, $project) = $this->Lighthouse->projectId($project);

		$tickets = $this->Lighthouse->tickets($project);
		debug ($tickets);
	}

	public function ticket($project, $id) {
		list($account, $project) = $this->Lighthouse->projectId($project);
	}

	public function milestone($project, $id) {
		list($account, $project) = $this->Lighthouse->projectId($project);
	}

	public function page($project, $id) {
		list($account, $project) = $this->Lighthouse->projectId($project);
	}

	protected function _process($account, $project, $type, $id) {
	}

	protected function _skip($account, $project, $type, $id) {
	}

	protected function _accept($account, $project, $type, $id) {
	}
}
