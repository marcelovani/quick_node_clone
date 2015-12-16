CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Requirements
 * Installation
 * Maintainers

INTRODUCTION
------------

Quick Node Clone is meant as a way in Drupal 8 to clone nodes. It currently supports cloning of the following fields on nodes:

    Textfield
    Textarea
    Taxonomy / Entity Reference
    Inline Entity Form (from the inline_entity_form module)
    Select

It can be easily modified to support more as well.

The module adds a "Clone (Quick)" tab to a node. When clicked, a new node is created and fields from the previous node are populated into the new fields.

This may be duplicate work of node_clone, but as of this writing (12/15/15) they don't have a D8 version and this code was created for a project from scratch in a reusable manner. This is meant to support different field types like inline_entity_form easily.


REQUIREMENTS
------------

This module requires the following modules:

 * Node


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module.
 * Visit a node view page to clone it with the Clone (Quick) tab.


MAINTAINERS
-----------

Current maintainers:
 * David Lohmeyer (Vilepickle) - https://www.drupal.org/user/783006
