<?php
App::uses('Folder', 'Utility');

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
		'Lighthouse',
		'Renumber',
		'Review',
	];

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->description('Process a lighthouse account export')
		->addSubCommand('load', array(
			'help' => 'Load a lighthouse account export file',
			'parser' => parent::getOptionParser()
				->addArgument('export file', array(
					'help' => 'The account export file from lighthouse',
					'required' => true
				))
		))
		->addSubCommand('renumber', array(
			'help' => 'Rename export files so they are in numerical order',
			'parser' => parent::getOptionParser()
				->addArgument('project', array(
					'help' => 'Project name',
					'required' => false
				))
		))
		->addSubCommand('review', array(
			'help' => 'Review tickets to decide what to do',
			'parser' => parent::getOptionParser()
				->addArgument('project', array(
					'help' => 'Project name',
					'required' => false
				))
		));

		/*
		->addSubCommand('accounts', array(
			'help' => 'List lighthouse accounts already loaded',
		))
		->addSubCommand('projects', array(
			'help' => 'List projects for a given account',
			'parser' => parent::getOptionParser()
				->addArgument('account', array(
					'help' => 'The account name',
					'required' => true
				))
		));

		foreach (array('tickets', 'pages', 'milestones') as $type) {
			$parser->addSubCommand($type, array(
				'help' => "List $type for a given project",
				'parser' => parent::getOptionParser()
					->addArgument('account', array(
						'help' => 'The account name',
						'required' => true
					))
					->addArgument('project', array(
						'help' => 'The project identifier or name',
						'required' => true
					))
			));
		}
		*/
		return $parser;
	}

	public function initialize() {
		$folders = array(
			'export',
			'skipped',
			'import'
		);
		foreach ($folders as $folder) {
			if (!is_dir($folder)) {
				mkdir($folder, 0777, true);
			}
		}
	}

	public function load() {
		$this->Lighthouse->load($this->args[0]);
	}
}
