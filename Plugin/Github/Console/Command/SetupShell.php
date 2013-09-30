<?php

class SetupShell extends AppShell {

	protected $_lhConfig = [];

	protected $_config = [];

	public $uses = [
		'Lighthouse.LHProject',
	];

	public function getOptionParser() {
		$parser = new ConsoleOptionParser('Github.setup');
		$parser
			->description('Preparotory steps before running an import')
			->epilog('Lighthouse and github user names typically don\'t match, create the map of user names so that tickets/comments are attributed to the appropriate user');

		return $parser;
	}

	public function main() {
		if (file_exists('Config/lighthouse.php')) {
			Configure::load('lighthouse');
		}
		if (file_exists('Config/github.php')) {
			Configure::load('github');
		}

		$this->_lhConfig = Configure::read('Lighthouse');
		$this->_config = Configure::read('Github');

		$this->projects();
		$this->users();
		$this->token();

		$this->_dump('github', $this->_config);
	}

	public function token() {
		if (empty($this->_config['token'])) {
			$this->_config['token'] = '';
			while (!$this->_config['token']) {
				$this->_config['token'] = $this->in('Please enter the github token to use');
			}
		}

		$this->out(sprintf('Github token set to "%s"', $this->_config['token']), 1, Shell::VERBOSE);
	}

	public function users() {
		foreach ($this->_lhConfig['users'] as $name) {
			if (isset($this->_config['users'][$name])) {
				$this->out(sprintf('Skipping %s, already defined with github username %s', $name, $this->_config['users'][$name]), 1, Shell::VERBOSE);
				continue;
			}
			$username = $this->in("github username for '$name' ?");
			$this->_config['users'][$name] = $username;
		}
	}

	public function projects() {
		$this->LHProject->source('accepted');
		foreach ($this->LHProject->all() as $id) {
			list($account, $project) = $this->LHProject->project($id);

			if (isset($this->_config['projects'][$account][$project])) {
				$ghProject = $this->_config['projects'][$account][$project]['account'] . '/' .
					$this->_config['projects'][$account][$project]['project'];
				$this->out(sprintf('Skipping %s, already mapped to github project %s', $id, $ghProject), 1, Shell::VERBOSE);
				continue;
			}

			$ghAccount = $ghProject = false;
			$response = $this->in("Github account/project for importing $account/$project?");
			if ($response) {
				list($ghAccount, $ghProject) = explode('/', $response);
			}
			$this->_config['projects'][$account][$project] = [
				'account' => $ghAccount,
				'project' => $ghProject
			];
		}
	}

	protected function _dump($name, array $data) {
		$filename = strtolower($name);
		$name = ucfirst($filename);

		if ($data === Configure::read($name)) {
			$this->out(sprintf('No changes made to %s config', $name), 1, Shell::VERBOSE);
			return false;
		}

		$this->out(sprintf('Updating %s config, review Config/%s.php to make any changes', $name, $filename));

		$string = "<?php\n\n\$config = ['$name' => " . var_export($data, true) . "];\n";

		$File = new File("Config/$filename.php", true);
		$File->write($string);

		return $name;
	}
}
