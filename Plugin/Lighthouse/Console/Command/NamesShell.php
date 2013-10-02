<?php
App::uses('SkipShell', 'Lighthouse.Console/Command');

class NamesShell extends AppShell {

	public $uses = [
		'Lighthouse.LHProject',
		'Lighthouse.LHTicket'
	];

	protected $_users = [];

	public function getOptionParser() {
		$parser = Shell::getOptionParser();
		$parser
			->description('Extract user names from approved lighthouse tickets')
			->epilog('Adds the usernames from all users in approved tickets to the lighthouse user name list. This permits import processes to map lighthouse usernames to imported-system usernames');
		return $parser;
	}

	public function main() {
		if (!$this->_users && file_exists('Config/lighthouse.php')) {
			Configure::load('lighthouse');
			$this->_users = Configure::read('Lighthouse.users');
		}

		$this->LHProject->source('accepted');
		foreach ($this->LHProject->all() as $id) {
			$this->process($id);
		}

		$this->_write();
	}

	public function process($project) {
		$this->out(sprintf('Processing %s', $project));

		foreach ($this->LHTicket->all($project) as $id) {
			$this->out(sprintf(' * Processing %s', $id));
			$data = $this->LHTicket->data($id);

			$this->_add($data['ticket']['created_by']);

			foreach ($data['comments'] as $comment) {
				$this->_add($comment['user_name']);
				$this->_add($comment['creator_name']);
			}
		}
	}

	protected function _add($username) {
		if (!$username || in_array($username, $this->_users)) {
			return;
		}

		$this->out("Adding user $username", 1, Shell::VERBOSE);
		$this->_users[$username] = $username;
	}

	protected function _write() {
		Configure::write('Lighthouse.users', $this->_users);
		$string = "<?php\n\n\$config = ['Lighthouse' => " . var_export(Configure::read('Lighthouse'), true) . "];\n";

		$File = new File('Config/lighthouse.php', true);
		$File->write($string);
	}
}
