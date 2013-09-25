<?php

class SetupShell extends AppShell {

	protected $_lhConfig = [];

	protected $_config = [];

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

		$this->token();
		$this->users();

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
