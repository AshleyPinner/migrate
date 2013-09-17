<?php
App::uses('Shell', 'Console/Command');

class AppTask extends Shell {

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->addArgument('project', array(
				'help' => 'Project name',
				'required' => false
			));
		return $parser;
	}
}
