<?php

class AppShell extends Shell {

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->addArgument('project', array(
				'help' => 'Project name',
				'required' => false
			));
		return $parser;
	}

	protected function _welcome() {
	}

}
