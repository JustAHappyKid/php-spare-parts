<?php

require_once 'url.php';

function testTakingPartsOfURL() {
  $url = 'http://tabcollab.net/tabs/nada-surf/inside-of-love';
  assertEqual('tabcollab.net', takeDomain($url));
  assertEqual('/tabs/nada-surf/inside-of-love', takePath($url));
  assertEqual('/index.cfm',
    takePath('https://long.house.gov/index.cfm?sectionid=58&sectiontree=3,58'));
}

function testConstructUrlFromRelativeLocation() {
  $cases = array(
    array('http://somesite.fake/contact.html', 'contact-submit.html',
          'http://somesite.fake/contact-submit.html'),
    array('http://www.lewrockwell.com/rogers-j/rogers-j17.html', 'rogers-j12.html',
          'http://www.lewrockwell.com/rogers-j/rogers-j12.html'),
    array('http://thepicklefactory.cjb.net/artists/', 'benjamin_wagner.html',
          'http://thepicklefactory.cjb.net/artists/benjamin_wagner.html'),
    array('http://somefunnypranks.com/', 'http://www.downsizedc.org',
          'http://www.downsizedc.org'),
    array('http://somefunnypranks.com/others/', './', 'http://somefunnypranks.com/others/'),
    array('http://www.kitco.com/charts/livegold.html', './', 'http://www.kitco.com/charts/'),
    array('http://www.kitco.com/charts/livesilver.html', '../kitco-gold-index.html',
          'http://www.kitco.com/kitco-gold-index.html'),
    array('http://cocktailsail.com', 'path/to/', 'http://cocktailsail.com/path/to/'));
  foreach ($cases as $case) {
    $expected = $case[2];
    $actual = constructUrlFromRelativeLocation($case[0], $case[1]);
    if ($actual != $expected) {
      fail("Expected to get URL $expected but got $actual");
    }
  }

  assertEqual('https://tabcollab.net/add-tab',
    constructUrlFromRelativeLocation('http://tabcollab.net/', 'add-tab', $secure = true));
  assertEqual('http://tabcollab.net/add-tab',
    constructUrlFromRelativeLocation('https://tabcollab.net/', 'add-tab', $secure = false));
  assertEqual('https://cocktailsail.com/yummy/treat',
    constructUrlFromRelativeLocation('https://cocktailsail.com/yummy/stuff', 'treat',
                                     $secure = null));

  try {
    constructUrlFromRelativeLocation('http://example.org/', null);
    fail('constructUrlFromRelativeLocation should raise exception when one of its parameters ' .
         'is not a string');
  } catch (InvalidArgumentException $e) {
    # That's what we're expecting...
  }
}

function testReadQueryFromURI() {
  assertEqual(array(), readQueryFromURI('/path/without/query'));
  assertEqual(array(), readQueryFromURI('/path/without-query/but-with-question-mark?'));
}

function testTitleToUrlComponent() {
  $cases = array(
    '"Unschool Your Child" and give them an edge' => 'unschool-your-child-and-give-them-an-edge',
    'over Government-Schooled children' => 'over-government-schooled-children',
    "Stan's big day" => 'stans-big-day');
  foreach ($cases as $in => $out) {
    assertEqual($out, titleToUrlComponent($in));
  }
}
