<?php
App::uses('SkipTask', 'Console/Command/Task');

class AcceptTask extends SkipTask {

	protected $_pathPrefix = 'accepted/';

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->description('Accept tickets by state');
		return $parser;
	}

}
