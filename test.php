<?php

  require( 'psn.inc.php' );

  $psn = new PSN\Client();
  $psn->findJID( 'salakala', PSN_Region_EU );
