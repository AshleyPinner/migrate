<?php

App::uses('Folder', 'Utility');
App::uses('LighthouseAppModel', 'Lighthouse.Model');

class LHTicket extends LighthouseAppModel {

	public $belongsTo = [
		'Lighthouse.LHProject'
	];

	public function all() {
		list($account, $project) = $this->project();

		$Folder = new Folder($this->source() . $account . '/projects/' . $project . '/tickets');
		list($tickets) = $Folder->read();
		return $tickets;
	}

	public function data($id) {
		list($account, $project) = $this->projectId($project);
		$path = $this->source() . $account . '/projects/' . $project . '/tickets/' . $id . '/ticket.json';

		$return = $this->_read($path);

		if ($this->source() !== 'data/accepted/') {
			$return = current($return);
		}
		return $return;
	}
}
