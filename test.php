<?php

  require( 'psn.inc.php' );

  $psn = new PSN\Client();
  $psn->findJID( 'noorutin', PSN_Region_EU );
