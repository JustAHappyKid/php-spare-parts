<?php

require_once 'url.php';
use \SpareParts\URL;

function testTakingPartsOfURL() {
  $url = 'http://tabcollab.net/tabs/nada-surf/inside-of-love';
  assertEqual('tabcollab.net', URL\takeDomain($url));
  assertEqual('/tabs/nada-surf/inside-of-love', URL\takePath($url));
  assertEqual('/index.cfm',
    URL\takePath('https://long.house.gov/index.cfm?sectionid=58&sectiontree=3,58'));
  assertEqual('v1=this&othervar=that', URL\takeQuery('http://test.org/?v1=this&othervar=that'));
  assertEqual(null, URL\takeQuery('http://test.net/no/query'));
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
    $actual = URL\constructUrlFromRelativeLocation($case[0], $case[1]);
    if ($actual != $expected) {
      fail("Expected to get URL $expected but got $actual");
    }
  }

  assertEqual('https://tabcollab.net/add-tab',
    URL\constructUrlFromRelativeLocation('http://tabcollab.net/', 'add-tab', $secure = true));
  assertEqual('http://tabcollab.net/add-tab',
    URL\constructUrlFromRelativeLocation('https://tabcollab.net/', 'add-tab', $secure = false));
  assertEqual('https://cocktailsail.com/yummy/treat',
    URL\constructUrlFromRelativeLocation('https://cocktailsail.com/yummy/stuff', 'treat',
                                         $secure = null));

  try {
    URL\constructUrlFromRelativeLocation('http://example.org/', null);
    fail('constructUrlFromRelativeLocation should raise exception when one of its parameters ' .
         'is not a string');
  } catch (InvalidArgumentException $e) {
    # That's what we're expecting...
  }
}

function testReadQueryFromURI() {
  assertEqual(array(), URL\readQueryFromURI('/path/without/query'));
  assertEqual(array(), URL\readQueryFromURI('/path/without-query/but-with-question-mark?'));
  assertEqual(array('a' => '10245', 'b' => 'something'),
    URL\readQueryFromURI('/path/with-query?a=10245&b=something'));
  assertEqual(array('query' => ''), URL\readQueryFromURI('/okay?query='));
  $r = URL\readQueryFromURI('/path?noEqualSign');
  assertTrue($r == array('noEqualSign' => '') || $r == array('noEqualSign' => null));
  assertThrows('InvalidArgumentException', function() { URL\readQueryFromURI('/?a=b=c'); });
}

function testTitleToUrlComponent() {
  $cases = array(
    '"Unschool Your Child" and give them an edge' => 'unschool-your-child-and-give-them-an-edge',
    'over Government-Schooled children' => 'over-government-schooled-children',
    "Stan's big day" => 'stans-big-day');
  foreach ($cases as $in => $out) {
    assertEqual($out, URL\titleToUrlComponent($in));
  }
}
