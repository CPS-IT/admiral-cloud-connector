..  include:: /Includes.rst.txt

..  _quickStart:

============
Installation
============

..  rst-class:: bignums

#.  Install the extension

    Install the extension with composer:

    ..  code-block:: bash

        composer require cpsit/admiral-cloud-connector

    Afterwards run the following SQL statement:

    ..  code-block:: sql

        INSERT INTO `sys_file_storage` (`pid`, `cruser_id`, `deleted`, `description`, `name`, `driver`, `configuration`, `is_default`, `is_browsable`, `is_public`, `is_writable`, `is_online`, `auto_extract_metadata`, `processingfolder`) VALUES
        (0, 0, 0, 'Automatically created during the installation of EXT:admiral_cloud_connector', 'AdmiralCloud', 'AdmiralCloud', '', 0, 1, 1, 0, 1, 1, '1:/_processed_/');

    Alternatively you can create the storage manually via list plugin on the root page. Choose *AdmiralCloud*
    from the Driver's list and set "Folder for manipulated and temporary images etc." to *1:/_processed_/*.

#.  System configuration

    Once you have set up a contract with AdmiralCloud, you will receive your login credential by mail and SMS.
    Add the required configuration to :file:`config/system/settings.php` or :file:`config/system/additional.php`.

    ..  seealso::

        :ref:`Learn more about how configure AdmiralCloud credentials. <SystemSettings>`

#.  Initial setup of user groups

    Send a list of your user groups to AdmiralCloud and set up AC SecurityGroups in the backend.

    ..  seealso::

        :ref:`Learn more about how to set up user groups. <AcSecGroup>`

#.  Setting up a file mount

    You have to create fileMount "AdmiralCloud" for the storage.

    ..  seealso::

        :ref:`Learn more about how to setup the file storage. <FileMount>`

#.  User configuration

    No configuration is needed for editors. Administrators need the Security Group for confirmation.

    ..  seealso::

        :ref:`Learn more about user management. <UserConfiguration>`

#.  LinkHandler Configuration

    A LinkHandler configuration is included automatically. No manual configuration steps are necessary.

    You can find the LinkHandler Configuration here: :file:`EXT:admiral_cloud_connector/Configuration/TSconfig/LinkHandler.tsconfig`

Read more
=========

..  toctree::
    :maxdepth: 2
    :titlesonly:

    SystemSettings
    AcSecGroup
    FileMount
    UserConfiguration
