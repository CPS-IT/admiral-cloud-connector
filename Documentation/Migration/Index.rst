..  include:: /Includes.rst.txt

..  _migration:

=========
Migration
=========

This page lists required migration steps when upgrading to a new major version
of the extension.

..  _version-5.0.0:

Version 5.0.0
==============

Version 5.0.0 removes the dedicated *"Update metadata of AdmiralCloud files"*
Scheduler task. Updating metadata is now done via the console command
:shell:`admiral-cloud:update-metadata`.

..  _migration-5-scheduler-task:

Converting the Scheduler task
-------------------------------

#.  Open the backend module *Administration > Scheduler*.

#.  Note down the action type currently configured on your existing
    *"Update metadata of AdmiralCloud files"* task (either *"Update all"* or
    *"Update last changed"*), then delete the task. It can no longer run after
    the upgrade.

#.  Add a new Scheduler task and choose task type *"admiral-cloud:update-metadata"*
    (grouped under *"admiral-cloud"* in the task type selector).

#.  In the *"Command Configuration"* field, set the :shell:`actionType`
    argument depending on the action type noted in step 2:

    ..  list-table::
        :header-rows: 1

        *   -   Previous action type
            -   :shell:`actionType` argument
        *   -   Update last changed
            -   :shell:`lastChanged` (default, can also be left empty)
        *   -   Update all
            -   :shell:`all`

#.  Configure the same frequency/start date as the old task and save.

..  tip::

    The command can also be run directly on the command line, e.g. for testing
    or when scheduling via system cron instead of TYPO3's Scheduler:

    ..  code-block:: bash

        vendor/bin/typo3 admiral-cloud:update-metadata lastChanged
