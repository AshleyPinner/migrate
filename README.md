# Migrate

This is a migration tool for moving tickets from lighthouse app to other systems.

##Installation

	git clone https://github.com/lh-import/migrate.git
	composer install

This will download the required code and setup dependencies.

## How to use

If you are familiar with cakephp, or even if you are not, you can dive straight in and you'll be
guided through the process:

	$ Console/cake

	Lighthouse migration shell

	For help with each command append `--help`. The commands should
	be called in the order shown, each asks for confirmation before
	doing anything for your own piece of mind. To get more
	information about what the shell is doing, use the `--verbose`
	flag
	---------------------------------------------------------------

	Lighthouse functions:

	Console/cake lighthouse.load
	Console/cake lighthouse.renumber
	Console/cake lighthouse.accept
	Console/cake lighthouse.skip (optional)
	Console/cake lighthouse.review (optional)
	Console/cake lighthouse.names (optional)
	---------------------------------------------------------------

	Github functions:

	Console/cake github.setup
	Console/cake github.import
	---------------------------------------------------------------

	$

##Importing data from Lighthouse

Existing tickets must be exported from Lighthouse using the administrative "account export" tool.
This can be found at https://your-account.lighthouseapp.com/export. Lighthouse will then mail
an export file which is the input to the migration process.

Required steps to set things up are to run the following commands:

    Console/cake lighthouse.load <export file>
    Console/cake lighthouse.renumber
    Console/cake lighthouse.accept [--open] [--closed]

One or both of `--open`, `--closed` must be specified. This will create copies of the export
files with the data in the format used by the import process using the ticket open/closed state
as the primary means of determining which tickets to import.


###Review data

Optionally after this, it's recommended to review the data that will be imported.
This gives the possibility to remove individual tickets, or a whole user's activity from the
data to be imported. Tickets, and comments are presented for review like so:

	Console/cake lighthouse.review

    Ticket 1: A real ticket title
    http://your-project.lighthouse.com/projects/123/1

    The ticket text is presented here
    so that you can review the ticket and decide
	if it should be imported or skipped

	Approve this ticket by someuser? y/n/Y/N

There are four possible responses, y(es), n(o) and Y(ES), N(O). Answering `y` will leave the
ticket to be imported; Answering `Y` (capital letter) will accept this ticket and pre-approve
all activity by `someuser`. By contrast, answering `n` will remove the ticket from the set of
tickets to be imported, answering `N` will also blacklist `someuser` so that all of their
tickets (and comments) are marked as spam and not imported.

All tickets are processed in this way, and then all comments also. If the lighthouse project
is public be aware that there is no indication in an account export file of tickets or comments
which are identified as spam via the web interface.

###Extract user names

The final optional step is to extract usernames from lighthouse activity to permit attribution
to equivalent users in the destination ticket system. The names of assigned users are
automatically extracted during the `accept` shell, this step none-interactively adds all
usernames for all tickets/comments to the list of known usernames.

##Setup github

To use the shell to import tickets to github, first run the setup shell:

    Console/cake github.setup

The shell will do 3 things:

 * Prompt for a github oauth token if there isn't one defined
 * Map lighthouse projects to github projects (i.e. where the tickets should be imported to)
 * Map lighthouse usernames to github usernames

The oauth token is obtained from [your account page on github](https://github.com/settings/tokens/new). 
This is required to be able to use github's api.

Mapping projects to github projects defines which projects will be processed. If there is no
map defined the import process will not touch the pre-processed lighthouse project data.

Mapping usernames is not required and this step can be aborted if desired, the only concequence
is that usernames will be displayed as plain text instead of being a link to the github-user's
profile.

##Import to github

It's recommended that before running the import script in anger - it is run against a fork
of the project to check that imported tickets/comments are created as expected. The shell
is intended to be failure tollerant and can be restarted should it fail to complete and pick
up where it left off. The import process will create labels and milestones as required, to do
this however it's necessary for the user running the import script (the user who corresponds to
the api token provided in the previous step) to have commit rights to the repository.

To start the import process, just specify the project name:

    Console/cake github.import projectname
    Github issue lh-import/example #1 created for ticket A real ticket title
