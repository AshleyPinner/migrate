<?php

class ResetShell extends AppShell {

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

	protected $_config = [];

	protected $_path = 'accepted/';

	protected $_labels = [];

	protected $_milestones = [];

	protected $_users = [];

	public $tasks = [
		'Lighthouse.LH',
	];

	public function getOptionParser() {
		$parser = new ConsoleOptionParser('Github.reset');
		$parser
			->description('Reset data to import again')
			->addArgument('project', array(
				'help' => 'Project name',
				'required' => true
			))
			->epilog('The import script is written such that it will not import tickets/comments that have already been imported - this permits the import process to be restarted should it fail to complete without the risk of creating duplicate tickets/comments. This shell removes the github-specific markers from the data to be imported, permitting the import process to be re-run.');

		return $parser;
	}

	public function main() {
		$this->LH->source('accepted');

		if (!$this->args) {
			$this->args = $this->LH->projects();
		}

		foreach ($this->args as $project) {
			$this->reset($project);
		}
	}

	public function reset($project) {
		list($account, $project) = $this->LH->projectId($project);

		$pid = "$account/$project";
		$tickets = $this->LH->tickets($pid);
		foreach ($tickets as $id) {
			$data = $this->LH->ticket($pid, $id);

			if (!$data) {
				$this->out(sprintf('<error>Skipping invalid ticket with id %s</error>', $id));
				continue;
			}

			$update = false;
			if (isset($data['github'])) {
				$update = true;
				unset($data['github']);
			}

			foreach ($data['comments'] as &$comment) {
				if (isset($comment['github'])) {
					$update = true;
					unset($comment['github']);
				}
			}

			if ($update) {
				$this->out(sprintf('Removing github data from %s ticket %s: %s', $pid, $data['ticket']['id'], $data['ticket']['title']));
				$this->LH->updateTicket($pid, $data);
			} else {
				$this->out(
					sprintf('No github data found for %s ticket %s: %s', $pid, $data['ticket']['id'], $data['ticket']['title']),
					1,
					Shell::VERBOSE
				);
			}
		}
	}
}
