<?php
/**
* @package wp-sparkpost
*/
namespace WPSparkPost;
use \Nyholm\NSA;
use \Mockery;
use phpmock\phpunit\PHPMock;

class TestHttpMailer extends \WP_UnitTestCase {
  use PHPMock;

  var $mailer;

  function setUp() {
    $this->mailer = new SparkPostHTTPMailer();
  }

  public function tearDown() {
     \Mockery::close();
  }

  function test_mailSend_calls_sparkpost_send() {
    $stub = Mockery::mock($this->mailer);
    $stub->shouldReceive('sparkpost_send')->andReturn('woowoo');

    $this->assertTrue(NSA::invokeMethod($stub, 'mailSend', null, null) == 'woowoo');
  }

  function test_mailer_is_a_mailer_instance() {
    $this->assertTrue( $this->mailer instanceof \PHPMailer );
  }

  function test_get_sender_with_name() {
    $this->mailer->setFrom( 'me@hello.com', 'me' );
    $sender = array(
      'name' => 'me',
      'email' => 'me@hello.com'
    );

    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_sender') == $sender);
  }

  function test_get_sender_without_name() {
    $this->mailer->setFrom( 'me@hello.com', '' );
    $sender = array(
      'email' => 'me@hello.com'
    );

    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_sender') == $sender);
  }

  function test_get_request_headers() {
    $expected = array(
      'User-Agent' => 'wordpress-sparkpost/' . WPSP_PLUGIN_VERSION,
      'Content-Type' => 'application/json',
      'Authorization' => ''
    );
    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_request_headers') == $expected);

    NSA::setProperty($this->mailer, 'settings', array('password' => 'abcd1234'));
    $expected = array(
      'User-Agent' => 'wordpress-sparkpost/' . WPSP_PLUGIN_VERSION,
      'Content-Type' => 'application/json',
      'Authorization' => 'abcd1234'
    );
    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_request_headers') == $expected);
  }

  function test_get_request_headers_obfuscate_key() {
    NSA::setProperty($this->mailer, 'settings', array('password' => 'abcd1234'));
    $expected = array(
      'User-Agent' => 'wordpress-sparkpost/' . WPSP_PLUGIN_VERSION,
      'Content-Type' => 'application/json',
      'Authorization' => 'abcd'.str_repeat('*', 36)
    );
    $this->assertTrue(NSA::invokeMethod($this->mailer, 'get_request_headers', true) == $expected);
  }

  function test_get_headers() {
    $raw_headers = "Date: Wed, 26 Oct 2016 23:45:32 +0000
    To: undisclosed-recipients:;
    From: Root User <root@localhost>
    Subject: Hello
    Reply-To: replyto@mydomain.com
    Message-ID: <abcd@example.org>
    MIME-Version: 1.0
    Content-Type: text/plain; charset=iso-8859-1
    Content-Transfer-Encoding: 8bit";

    $expected = array(
      'Message-ID' => '<abcd@example.org>',
      'Date' => 'Wed, 26 Oct 2016 23:45:32 +0000'
    );
    $stub = Mockery::mock($this->mailer);
    $stub->shouldReceive('createHeader')->andReturn($raw_headers);
    $formatted_headers = NSA::invokeMethod($stub, 'get_headers');

    $this->assertTrue($formatted_headers == $expected);
  }


  function test_get_headers_should_include_cc_if_exists() {
    $raw_headers = "Date: Wed, 26 Oct 2016 23:45:32 +0000
    Reply-To: replyto@mydomain.com";

    $expected = array(
      'Date' => 'Wed, 26 Oct 2016 23:45:32 +0000',
      'CC' => 'hello@abc.com,Name <name@domain.com>'
    );
    $stub = Mockery::mock($this->mailer);
    $stub->shouldReceive('createHeader')->andReturn($raw_headers);
    $stub->addCc('hello@abc.com');
    $stub->addCc('name@domain.com', 'Name');

    $formatted_headers = NSA::invokeMethod($stub, 'get_headers');

    $this->assertTrue($formatted_headers == $expected);
  }

  function test_get_recipients() {
    $this->mailer->addAddress('to@abc.com');
    $this->mailer->addAddress('to1@abc.com', 'to1');
    $this->mailer->addCc('cc@abc.com');
    $this->mailer->addCc('cc1@abc.com', 'cc1');
    $this->mailer->addBcc('bcc@abc.com');
    $this->mailer->addBcc('bcc1@abc.com', 'bcc1');

    $header_to = implode(', ', [
      'to@abc.com',
      'to1 <to1@abc.com>',
    ]);

    $expected = [
      [
        'address' => [
          'email' => 'to@abc.com',
          'header_to' => $header_to
        ]
      ],
      [
        'address' => [
          'email' => 'to1@abc.com',
          'header_to' => $header_to
        ]
      ],
      [
        'address' => [
          'email' => 'bcc@abc.com',
          'header_to' => $header_to
        ]
      ],
      [
        'address' => [
          'email' => 'bcc1@abc.com',
          'header_to' => $header_to
        ]
      ],
      [
        'address' => [
          'email' => 'cc@abc.com',
          'header_to' => $header_to
        ]
      ],
      [
        'address' => [
          'email' => 'cc1@abc.com',
          'header_to' => $header_to
        ]
      ]
    ];

    $recipients = NSA::invokeMethod($this->mailer, 'get_recipients');
    $this->assertTrue($recipients == $expected);
  }

  function test_get_attachments() {
    $temp = tempnam('/tmp', 'php-wordpress-sparkpost');
    file_put_contents($temp, 'TEST');
    $this->mailer->addAttachment($temp);
    $attachments = NSA::invokeMethod($this->mailer, 'get_attachments');
    $this->assertTrue($attachments[0]['type'] === 'application/octet-stream');
    $this->assertTrue($attachments[0]['name'] === basename($temp));
    $this->assertTrue($attachments[0]['data'] === base64_encode('TEST'));
    unlink($temp);
  }

  function test_isMail() {
    // test if isMail sets correct mailer
    $this->mailer->Mailer = 'abc';
    $this->assertTrue($this->mailer->Mailer === 'abc');
    $this->mailer->isMail();
    $this->assertTrue($this->mailer->Mailer === 'sparkpost');
  }

  function test_get_request_body_without_template() {
    // WITHOUT TEMPLATE
    $this->mailer->addAddress('abc@xyz.com', 'abc');
    $this->mailer->addBcc('bcc@xyz.com', 'bcc');
    $this->mailer->addCc('cc@xyz.com', 'cc');
    $this->mailer->setFrom( 'me@hello.com', 'me');

    NSA::setProperty($this->mailer, 'settings', [
      'enable_tracking' => true,
      'transactional' => false
    ]);

    $header_to = 'abc <abc@xyz.com>';
    $expected_request_body = [
      'recipients' => [
        [
          'address' => [
            'email' => 'abc@xyz.com',
            'header_to' => $header_to
          ]
        ],
        [
          'address' => [
            'email' => 'bcc@xyz.com',
            'header_to' => $header_to
          ]
        ],
        [
          'address' => [
            'email' => 'cc@xyz.com',
            'header_to' => $header_to
          ]
        ]
      ],
      'options' => [
        'open_tracking' => (bool) true,
        'click_tracking' => (bool) true,
        'transactional' => (bool) false
      ],
      'content' => [
        'from' => [
          'name' => 'me',
          'email' =>'me@hello.com'
        ],
        'subject' => '',
        'headers' => [],
        'text' => ''
      ]
    ];

    $actual = NSA::invokeMethod($this->mailer, 'get_request_body');
    // for simpler expectation reset content.headers to empty array.
    // alternative is to stub get_headers which isn't working expectedly
    $actual['content']['headers'] = [];
    $this->assertTrue($expected_request_body == $actual);

    //INCLUDE REPLYTO
    $this->mailer->addReplyTo('reply@abc.com', 'reply-to');
    $this->mailer->addCustomHeader('Reply-To', 'reply-to <reply@abc.com>'); //for below version v4.6
    $actual = NSA::invokeMethod($this->mailer, 'get_request_body');
    $actual['content']['headers'] = []; //see note above
    $expected_request_body['content']['reply_to'] = 'reply-to <reply@abc.com>';
    $this->assertTrue($expected_request_body == $actual);
  }

  function test_get_request_body_with_template() {
    $this->mailer->addAddress('abc@xyz.com', 'abc');
    $this->mailer->addBcc('bcc@xyz.com', 'bcc');
    $this->mailer->addCc('cc@xyz.com', 'cc');
    $this->mailer->setFrom( 'me@hello.com', 'me');
    $header_to = 'abc <abc@xyz.com>';
    NSA::setProperty($this->mailer, 'settings', [
      'enable_tracking' => true,
      'transactional' => false,
      'template'   => 'hello'
    ]);

    $expected_request_body = [
      'recipients' => [
        [
          'address' => [
            'email' => 'abc@xyz.com',
            'header_to' => $header_to
          ]
        ],
        [
          'address' => [
            'email' => 'bcc@xyz.com',
            'header_to' => $header_to
          ]
        ],
        [
          'address' => [
            'email' => 'cc@xyz.com',
            'header_to' => $header_to
          ]
        ]
      ],
      'options' => [
        'open_tracking' => (bool) true,
        'click_tracking' => (bool) true,
        'transactional' => (bool) false
      ],
      'content' => [
        'template_id' => 'hello',
      ],
      'substitution_data' => [
        'content' => '',
        'subject' => '',
        'from_name' => 'me',
        'from' => 'me <me@hello.com>',
        'from_localpart'  => 'me'
      ]
    ];

    $actual = NSA::invokeMethod($this->mailer, 'get_request_body');
    $this->assertTrue($expected_request_body == $actual);

    //INCLUDE REPLYTO
    $this->mailer->addReplyTo('reply@abc.com', 'reply-to');
    $this->mailer->addCustomHeader('Reply-To', 'reply-to <reply@abc.com>'); //for below version v4.6
    $actual = NSA::invokeMethod($this->mailer, 'get_request_body');
    $expected_request_body['substitution_data']['reply_to'] = 'reply-to <reply@abc.com>';
    $this->assertTrue($expected_request_body == $actual);
  }

  function sparkpost_send($num_rejected) {
    $this->mailer->addAddress('abc@xyz.com', 'abc');
    $response = array(
      'headers' => array(),
      'body' => json_encode(array(
        'results' => array(
          'total_rejected_recipients' => $num_rejected,
          'total_accepted_recipients' => 1,
          'id'  => 88388383737373
        )
      ))
    );
    $http_lib_mock = Mockery::mock('httplib', array('request' => $response ));
    $lib_mock = $this->getFunctionMock(__NAMESPACE__, '_wp_http_get_object');
    $lib_mock->expects($this->at(0))->willReturn($http_lib_mock);

    return $this->mailer->sparkpost_send();
  }

  function test_sparkpost_send_success() {
    $this->assertTrue($this->sparkpost_send(0));
  }

  function test_sparkpost_send_failure() {
    $this->assertFalse($this->sparkpost_send(1));
  }
}
