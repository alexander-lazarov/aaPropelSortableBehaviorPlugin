<?php

sfPropelBehavior::registerMethods('sortable', array(
  array('aaPropelSortableBehavior', 'moveUp'),
  array('aaPropelSortableBehavior', 'moveDown')
));

sfPropelBehavior::registerHooks('sortable', array(
  ':save:pre'   =>  array('aaPropelSortableBehavior', 'preSave')
));
