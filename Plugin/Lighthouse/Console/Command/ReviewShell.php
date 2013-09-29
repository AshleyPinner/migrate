<?php

class ReviewShell extends AppShell {

	public $uses = [
		'Lighthouse.LHProject',
		'Lighthouse.LHTicket'
	];

	public $settings = [
		'accept' => null,
		'open' => true,
		'closed' => false
	];

	protected $_config = [];

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description('Interactively review approved tickets')
			->epilog('Lighthouse tickets can be rife with spam, use this shell to review tickets and unapprove spam before the data is migrated to the target system. To reset the review process modify the `Config/lighthouse.php` deleting the spammers/whitelist information');

		return $parser;
	}

	public function main() {
		if (!$this->_config && file_exists('Config/lighthouse.php')) {
			Configure::load('lighthouse');
			$this->_config = (array)Configure::read('Lighthouse');
		}
		foreach (['users', 'spammers', 'whitelist'] as $key) {
			if (!isset($this->_config[$key])) {
				$this->_config[$key] = [];
			}
		}

		$this->LHProject->source('accepted');
		$projects = $this->args;
		if (!$projects) {
			$projects = $this->LHProject->all();
		}

		$settings = $this->settings;
		foreach ($projects as $project) {
			$this->settings = $settings;
			$this->project($project);
		}
	}

	public function project($project) {
		$this->tickets($project);
		$this->comments($project);
	}

	public function comments($project) {
		$settings = $this->settings;

		$tickets = $this->LHTicket->all($project);
		foreach ($tickets as $id) {
			$data = $this->LHTicket->data($id);
			if (!$data['comments']) {
				$this->out(sprintf('<info>No comments for %s</info>', $data['ticket']['title']), Shell::VERBOSE);
				continue;
			}

			$this->out(sprintf('Reviewing comments for %s', $data['ticket']['title']));

			$updated = false;
			foreach ($data['comments'] as $i => $comment) {
				if (!$this->comment($comment, $data)) {
					unset ($data['comments'][$i]);
					$data['spam'][$i] = $comment;
					$updated = true;
				}
			}

			if ($updated) {
				$this->out(sprintf(
					'<info>Updating ticket %s %s, removing spam comment(s)</info>',
					$data['ticket']['id'],
					$data['ticket']['title']
				));
				$this->LHTicket->update($id, $data);
			}
		}

		$this->settings = $settings;
	}

	public function comment($comment, $data) {
		$user = $comment['user_name'];
		$userId = $comment['user_id'];

		$accept = false;
		$verbosity = Shell::NORMAL;
		if (isset($this->_config['whitelist'][$userId])) {
			$accept = 'y';
			$verbosity = Shell::VERBOSE;
		}
		if (isset($this->_config['spammers'][$userId])) {
			$accept = 'n';
			$verbosity = Shell::VERBOSE;
		}

		if ($verbosity === Shell::NORMAL || !empty($this->params['verbose'])) {
			$this->clear();
		}
		$this->out(sprintf('Comment on ticket %s: %s', $data['ticket']['id'], $data['ticket']['title']), 1, $verbosity);
		$this->out($data['ticket']['link'], 2, $verbosity);
		$this->out(String::truncate($comment['body'], 800), 2, $verbosity);

		if (!$accept) {
			if ($this->_isSpam($comment)) {
				$accept = 'N';
			}
		}

		if (!$accept) {
			list($account) = $this->LHTicket->project();
			$profileLink = sprintf('https://%s.lighthouseapp.com/users/%d', $account, $userId);
			$accept = $this->in(sprintf('Approve this comment by %s (%s)?', $user, $profileLink), ['y', 'n', 'Y', 'N']);

			if ($accept === strtoupper($accept)) {
				$this->_alwaysUser($userId, $user, $accept);
			}
		}

		if (strtolower($accept) === 'y') {
			$this->out('<info>Comment accepted</info>', 1, $verbosity);
			return true;
		}

		$this->out('<warning>Comment rejected</warning>', 1, $verbosity);
		return false;
	}

	public function tickets($project) {
		$settings = $this->settings;

		$tickets = $this->LHTicket->all($project);
		foreach ($tickets as $id) {
			$this->ticket($id);
		}

		$this->settings = $settings;
	}

	public function ticket($id) {
		$data = $this->LHTicket->data($id);

		$user = $data['ticket']['user_name'];
		$userId = $data['ticket']['user_id'];

		$accept = false;
		$verbosity = Shell::NORMAL;
		if (isset($this->_config['whitelist'][$userId])) {
			$accept = 'y';
			$verbosity = Shell::VERBOSE;
		}
		if (isset($this->_config['spammers'][$userId])) {
			$accept = 'n';
			$verbosity = Shell::VERBOSE;
		}

		if ($verbosity === Shell::NORMAL || !empty($this->params['verbose'])) {
			$this->clear();
		}
		$this->out(sprintf('Ticket %s: %s', $data['ticket']['id'], $data['ticket']['title']), 1, $verbosity);
		$this->out($data['ticket']['link'], 2, $verbosity);
		$this->out(String::truncate($data['ticket']['body'], 800), 2, $verbosity);

		if (!$accept) {
			if ($this->_isSpam($data['ticket'])) {
				$accept = 'N';
			} else {
				$config = $this->LHProject->config();
				$default = $this->_trim($config['default_ticket_text']);
				if (trim($data['ticket']['body']) === $default) {
					// Probably spam, but also possibly a mistake
					$accept = 'n';
				}
			}
		}

		if (!$accept) {
			list($account) = $this->LHTicket->project();
			$profileLink = sprintf('https://%s.lighthouseapp.com/users/%d', $account, $userId);
			$accept = $this->in(sprintf('Approve this ticket by %s (%s)?', $user, $profileLink), ['y', 'n', 'Y', 'N']);

			if ($accept === strtoupper($accept)) {
				$this->_alwaysUser($userId, $user, $accept);
			}
		}

		if (strtolower($accept) === 'y') {
			$this->out('<info>Ticket accepted</info>', 1, $verbosity);
			return;
		}
		$this->_markTicketAsSpam($id, $verbosity);
	}

	protected function _markTicketAsSpam($id, $verbosity) {
		$path = $this->LHTicket->path($id, true);
		$spamPath = str_replace('accepted', 'spam', $path);

		$File = new File($spamPath, true);
		$File->copy($path);
		unlink($path);
		rmdir(dirname($path));

		$this->out('<warning>Ticket moved to spam</warning>', 1, $verbosity);
	}

/**
 * Mark a user as a spammer or an ok dude
 *
 * @param int $id
 * @param string $name
 * @param string $accept Y or N
 */
	protected function _alwaysUser($id, $name, $accept) {
		if ($accept === 'Y') {
			$this->_config['whitelist'][$id] = $name;
		} else {
			$this->_config['spammers'][$id] = $name;
			unset($this->_config['whitelist'][$id]);
			unset($this->_config['users'][$id]);
		}

		$this->_dump('lighthouse', $this->_config);
	}

/**
 * _isSpam
 *
 * Detect common/simple spam patterns so that the user doesn't need to review blatant junk
 *
 * @param array $data
 * @return bool
 */
	protected function _isSpam($data) {
		return (
			(isset($data['title']) && $data['title'] === $data['user_name']) || // Title is username
			preg_match('@^".+":https?://\S+$@', $this->_trim($data['body'])) || // Body is just a link
			preg_match('@^<a href=".+?">.*?</a>$@', $this->_trim($data['body'])) || // Body is just a link
			preg_match('@https?://\S+$@', $this->_trim($data['body'])) || // Body is just a link
			!$this->_trim(preg_replace('@\[.+?\]\(.+?\)@', '', $this->_trim($data['body']))) || // Body is all links
			!$this->_trim(strip_tags(preg_replace('@<a.*?/a>@', '', $this->_trim($data['body'])))) // Body is all links
		);
	}

/**
 * strip leading/trailing noise
 *
 * remove spaces, common seperators and things that look like spaces such as AO (none
 * breaking space)
 *
 * @param string $str
 * @return stringd
 */
	protected function _trim($str) {
		return trim($str, " \t\n\r\0\x0B-_~| ");
	}
}
