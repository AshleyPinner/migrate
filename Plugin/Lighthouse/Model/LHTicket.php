<?php

App::uses('Folder', 'Utility');
App::uses('LighthouseAppModel', 'Lighthouse.Model');

class LHTicket extends LighthouseAppModel {

	public function __construct($params = []) {
		$this->LHProject = ClassRegistry::init('Lighthouse.LHProject');
		parent::__construct($params);
	}

	public function all($project = null) {
		list($account, $project) = $this->project($project);

		$Folder = new Folder($this->source() . $account . '/projects/' . $project . '/' . $this->_type);
		list($return) = $Folder->read();
		return $return;
	}

	public function data($id, $project = null) {
		list($account, $project) = $this->project($project);

		$return = $this->_read($id);
		if (!$return) {
			return false;
		}

		return $return;
	}

	public function path($id, $full = false) {
		return parent::path($id, $full) . '/ticket.json';
	}

	public function status($id, $data = [], $project = null) {
		$config = $this->LHProject->config();
		if (is_string($data)) {
			$project = $data;
			$data = [];
		}

		$data = $data ?: $this->data($id, $project);
		$state = $data['ticket']['state'];

		$return = null;
		if (in_array($state, $config['open_states_list'])) {
			$return = 'open';
		} elseif (in_array($state, $config['closed_states_list'])) {
			$return = 'closed';
		}

		return $return;
	}

	protected function _read($id) {
		$return = parent::_read($id);
		return $return;
	}

}
