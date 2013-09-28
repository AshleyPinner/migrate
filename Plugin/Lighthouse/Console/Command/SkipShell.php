<?php

class SkipShell extends AppShell {

	public $tasks = ['Lighthouse.LH'];

	public $uses = [
		'Lighthouse.LHProject',
		'Lighthouse.LHTicket'
	];

	public $settings = [
		'open' => false,
		'closed' => false
	];

	protected $_pathPrefix = 'skipped/';

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description('Skip tickets by state')
			->addOption('open', array(
				'boolean' => true,
				'help' => 'All open tickets'
			))
			->addOption('closed', array(
				'boolean' => true,
				'help' => 'All closed tickets'
			))
			->epilog('Tickets can be skipped by state as a broad means of defining what to import.');

		return $parser;
	}

	public function main() {

		$this->settings['open'] = $this->params['open'];
		$this->settings['closed'] = $this->params['closed'];

		if (empty($this->settings['open']) && empty($this->settings['closed'])) {
			return $this->out($this->getOptionParser()->help());
		}

		$this->LHProject->source('renumbered');

		$projects = $this->args;
		if (!$projects) {
			$projects = $this->LHProject->all();
		}

		foreach ($projects as $project) {
			$this->processTickets($project);
		}
	}

	public function processTickets($project) {
		$this->out(sprintf('<info>Processing %s</info>', $project));
		$tickets = $this->LH->tickets($project);
		foreach ($tickets as $ticket) {
			$this->processTicket($ticket);
		}
	}

	public function processTicket($id) {
		$isSkipped = $this->LHTicket->is($id, 'skipped');
		$isAccepted = $this->LHTicket->is($id, 'accepted');
		$isSpam = $this->LHTicket->is($id, 'spam');

		$data = $this->LHTicket->data($id);
		$status = $this->LHTicket->status($id);
		$number = $data['ticket']['number'];
		$title = $data['ticket']['title'];

		if ($isSkipped || $isAccepted || $isSpam) {
			$what = $isAccepted ? 'accepted' : ($isSkipped ? 'skipped' : 'marked as spam');
			$this->out("<comment>Skipping ticket $number: $title, already $what</comment>", 1, Shell::VERBOSE);
			return;
		}

		if (
			($this->settings['open'] && $status === 'open') ||
			($this->settings['closed'] && $status === 'closed')
		) {
			$this->out("Processing ticket $number: $title");
			if ($this->name === 'Accept') {
				$this->LHProject->source('accepted');
			} else {
				$this->LHProject->source('skipped');
			}
			$this->LHTicket->update($id, $data);
			$this->LHProject->source('renumbered');
			return;
		}

		$this->out("<comment>Skipping ticket $number: $title, ticket is $status</comment>", 1, Shell::VERBOSE);
		return false;
	}
}
