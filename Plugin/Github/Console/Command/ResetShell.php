<?php

class ResetShell extends AppShell {

	public $uses = [
		'Lighthouse.LHProject',
		'Lighthouse.LHTicket',
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
		$this->LHProject->source('accepted');

		$projects = $this->args;
		foreach ($projects as $project) {
			$this->tickets($project);
		}
	}

	public function tickets($project) {
		list($account, $project) = $this->LHProject->project($project);
		$this->out(sprintf('Processing %s/%s', $account, $project));

		foreach ($this->LHTicket->all() as $id) {
			$this->ticket($id);
		}
	}

	public function ticket($id) {
		$data = $this->LHTicket->data($id);

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
			$this->out(sprintf('Removing github data from ticket %s: %s', $data['ticket']['id'], $data['ticket']['title']));
			return $this->LHTicket->update($id, $data);
		}

		$this->out(
			sprintf('No github data found for ticket %s: %s', $data['ticket']['id'], $data['ticket']['title']),
			1,
			Shell::VERBOSE
		);
		return false;
	}
}
