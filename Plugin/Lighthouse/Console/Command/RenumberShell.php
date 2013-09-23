<?php

class RenumberShell extends Shell {

	public $tasks = [
		'Lighthouse.LH',
		'Lighthouse.Renumber',
	];

	public function getOptionParser() {
		$parser = $this->Renumber->getOptionParser();

		return $parser;
	}

	public function main() {
		$this->Renumber->main();
	}

}
