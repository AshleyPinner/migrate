<?php
App::uses('Folder', 'Utility');
App::uses('LHTask', 'Console/Command/Task/LH');
App::uses('RenumberTask', 'Console/Command/Task/LH');
App::uses('AcceptTask', 'Console/Command/Task/LH');
App::uses('SkipTask', 'Console/Command/Task/LH');

class LighthouseShell extends Shell {

/**
 * Current account
 *
 * @var string
 */
	protected $_account;

/**
 * Current project
 *
 * @var string
 */
	protected $_project;

	public $tasks = [
		'Lighthouse.LH',
		'Lighthouse.Renumber',
		'Lighthouse.Accept',
		'Lighthouse.Skip',
		//'Review',
	];

	public function getOptionParser() {
		$load = $parser = parent::getOptionParser();
		$parser->description('Process a lighthouse account export')
		->addSubCommand('load', array(
			'help' => 'Load a lighthouse account export file',
			'parser' => $load->addArgument('export file', array(
					'help' => 'The account export file from lighthouse',
					'required' => true
				))
		))
		->addSubCommand('renumber', array(
			'help' => 'Rename export files so they are in numerical order',
			'parser' => $this->Renumber->getOptionParser()
		))
		->addSubCommand('accept', array(
			'help' => 'accept tickets by state',
			'parser' => $this->Accept->getOptionParser()
		))
		->addSubCommand('skip', array(
			'help' => 'skip tickets by state',
			'parser' => $this->Skip->getOptionParser()
		))
		/*
		->addSubCommand('review', array(
			'help' => 'Interactively review milestones, pages and tickets',
			'parser' => $this->Review->getOptionParser()
		))*/;

		return $parser;
	}

	public function load() {
		$this->LH->load($this->args[0]);
	}

}
