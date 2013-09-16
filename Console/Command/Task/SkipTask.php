<?php
App::uses('Accept', 'Console/Command/Task');

class SkipTask extends AcceptTask {

	protected $_pathPrefix = 'skipped/';

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->description('Skip tickets by state');
		return $parser;
	}

}
