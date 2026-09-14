..  include:: /Includes.rst.txt

..  _FileMount:

============
FileMount
============

A Filemount with AdmiralCloud has to be created in order to gain users access to AdmiralCloud.

Create a new filemount and choose the AdmiralCloud Filestorage.

..  image:: ../Images/filemount.png

If the Filestorage does not exist create the Filestorage manually.
It is important to select "AdmiralCloud" as driver.

If the Filestorage is setup properly you can use, you can create a Filemount.

..  important::

    It is essential to define a processing folder, e.g. *1:/_processed_/*. This folder
    **must** point to another file storage, e.g. *fileadmin*, since the AdmiralCloud
    storage itself cannot be used for processed files.

..  image:: ../Images/filestorage.png

When Filemount is setup properly, you have to assign the Filemount in the "Mounts & Workspaces" tab in the Usergroup or User properties:

..  image:: ../Images/assign-fm.png
