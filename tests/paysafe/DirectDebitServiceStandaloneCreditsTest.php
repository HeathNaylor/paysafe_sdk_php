<?php
/**
 * Created by PhpStorm.
 * Date: 12/12/17
 * Time: 11:06 AM
 */

namespace Paysafe;

use Paysafe\DirectDebit\StandaloneCredits;
use PHPUnit_Framework_Error_Notice;

/**
 * Class DirectDebitServiceStandaloneCreditsTest
 * This class provides coverage of the DirectDebitService::standaloneCredits function
 * @package Paysafe
 */
class DirectDebitServiceStandaloneCreditsTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject $mock_api_client */
    private $mock_api_client;

    public function setUp()
    {
        parent::setUp();

        $this->mock_api_client = $this->createMock(PaysafeApiClient::class);
        $this->mock_api_client->method('getAccount')->willReturn('bogus_account_num');
    }

    /**
     * This is a bad test as it simply confirms current undesirable behavior. If no type is specified in the
     * StandaloneCredits object (ach, eft, etc), then a PHP Error is generated. Ideally, the code would gracefully
     * handle this situation.
     * See: https://github.com/paysafegroup/paysafe_sdk_php/issues/13
     */
    public function testMissingBankInfo()
    {
        /*
         * The StandaloneCredits object passed to standaloneCredits() MUST have one of ach, eft, or bacs defined.
         */
        $dds = new DirectDebitService($this->mock_api_client);

        /*
         * TODO currently standaloneCredits() does not handle this situation gracefully and a PHP Error is emitted for
         * and undefined variable. Issue has been reported: https://github.com/paysafegroup/paysafe_sdk_php/issues/13
         */
        $this->expectException(PHPUnit_Framework_Error_Notice::class);
        $this->expectExceptionCode(8);
        $this->expectExceptionMessage('Undefined variable: return');
        $dds->standaloneCredits(new StandaloneCredits());
    }

    /*
     * This is a test to confirm that the DirectDebitService sets the required parameters we expect for a
     * standaloneCredits call.
     * If no token is specified, the service first checks that required Profile parameters were included.
     */
    public function testCreditAchMissingProfileRequiredFields()
    {
        $this->expectException(PaysafeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Missing required properties: firstName, lastName');

        $dds = new DirectDebitService($this->mock_api_client);
        /*
         * Note: we have to at least specify an empty ach or there will be a PHP Error.
         * See https://github.com/paysafegroup/paysafe_sdk_php/issues/13
         */
        $ach_credit_array = [ 'ach' => [] ];
        $dds->standaloneCredits(new StandaloneCredits($ach_credit_array));
    }

    /*
     * This is a test to confirm that the DirectDebitService sets the required parameters we expect for a
     * standaloneCredits call.
     * If a token is specified we expect to validate merchantRefNum, amount, and ach
     */
    public function testCreditAchMissingRequiredFieldsWithToken()
    {
        $this->expectException(PaysafeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Missing required properties: merchantRefNum, amount');

        $dds = new DirectDebitService($this->mock_api_client);
        $ach_credit_array = [
            'ach' => [
                'paymentToken' => 'bogus_payment_token',
            ]
        ];
        $dds->standaloneCredits(new StandaloneCredits($ach_credit_array));
    }

    /*
     * This is a test to confirm that the DirectDebitService sets the required parameters we expect for a
     * standaloneCredits call.
     * If no token is specified, but required Profile params are, we expect to validate merchantRefNum, amount, ach,
     * profile, and billingDetails
     */
    public function testCreditAchMissingRequiredFieldsNoToken()
    {
        $this->expectException(PaysafeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Missing required properties: merchantRefNum, amount, billingDetails');

        $dds = new DirectDebitService($this->mock_api_client);
        /*
         * Note: we have to at least specify an empty ach or there will be a PHP Error.
         * See https://github.com/paysafegroup/paysafe_sdk_php/issues/13
         */
        $ach_credit_array = [
            'ach' => [ ],
            'profile' => [
                'firstName' => 'firstname',
                'lastName' => 'lastname',
            ],
        ];
        $dds->standaloneCredits(new StandaloneCredits($ach_credit_array));
    }

    /*
     * This is a test to confirm that the DirectDebitService sets expected values for required/optional fields. If a
     * parameter is set in the StandaloneCredits obj, but not in the required or optional lists, it will be omitted from
     * the JSON created by toJson. (toJson is called by processRequest in the api client).
     *
     * So, we'll make our mock api client call toJson, and confirm the output lacks the field that doesn't appear in
     * required/optional.
     *
     * This test omits the token and instead includes profile information. The required/optional lists are built
     * slightly differently in each case
     */
    public function testCreditAchNoTokenInvalidField()
    {
        $this->mock_api_client
            ->expects($this->once())
            ->method('processRequest')
            ->with($this->isInstanceOf(Request::class))
            ->will($this->returnCallback(function (Request $param) {
                return json_decode($param->body->toJson(), true);
            }));
        $dds = new DirectDebitService($this->mock_api_client);

        $ach_credit_array = [
            'id' => 'id is a valid param, but not in required or optional list',
            'ach' => [
                'accountType' => 'CHECKING',
            ],
            'profile' => [
                'firstName' => 'firstname',
                'lastName' => 'lastname',
            ],
            'merchantRefNum' => 'merchantrefnum',
            'amount' => 555,
            'billingDetails' => [
                'zip' => '10007',
            ],
        ];

        $retval = $dds->standaloneCredits(new StandaloneCredits($ach_credit_array));
        $param_no_id = $ach_credit_array;
        unset($param_no_id['id']);
        $this->assertThat($retval->toJson(), $this->equalTo(json_encode($param_no_id)),
            'Did not receive expected return from DirectDebitService::standaloneCredits');
    }

    /*
     * This is a test to confirm that the DirectDebitService sets expected values for required/optional fields. If a
     * parameter is set in the StandaloneCredits obj, but not in the required or optional lists, it will be omitted from
     * the JSON created by toJson. (toJson is called by processRequest in the api client).
     *
     * So, we'll make our mock api client call toJson, and confirm the output lacks the field that doesn't appear in
     * required/optional.
     *
     * This test includes the token and omits profile information. The required/optional lists are built
     * slightly differently in each case
     */
    public function testCreditAchWithTokenInvalidField()
    {
        $this->mock_api_client
            ->expects($this->once())
            ->method('processRequest')
            ->with($this->isInstanceOf(Request::class))
            ->will($this->returnCallback(function (Request $param) {
                return json_decode($param->body->toJson(), true);
            }));
        $dds = new DirectDebitService($this->mock_api_client);

        $ach_credit_array = [
            'id' => 'id is a valid param, but not in required or optional list',
            'ach' => [
                'paymentToken' => 'myspecialtoken',
            ],
            'merchantRefNum' => 'merchantrefnum',
            'amount' => 555,
        ];

        $retval = $dds->standaloneCredits(new StandaloneCredits($ach_credit_array));
        $param_no_id = $ach_credit_array;
        unset($param_no_id['id']);
        $this->assertThat($retval->toJson(), $this->equalTo(json_encode($param_no_id)),
            'Did not receive expected return from DirectDebitService::standaloneCredits');
    }
    /*
     * This is a test to confirm that the DirectDebitService sets the required parameters we expect for a
     * standaloneCredits call.
     * If no token is specified, the service first checks that required Profile parameters were included.
     */
    public function testCreditEftMissingProfileRequiredFields()
    {
        $this->expectException(PaysafeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Missing required properties: firstName, lastName');

        $dds = new DirectDebitService($this->mock_api_client);
        /*
         * Note: we have to at least specify an empty eft or there will be a PHP Error.
         * See https://github.com/paysafegroup/paysafe_sdk_php/issues/13
         */
        $eft_credit_array = [ 'eft' => [] ];
        $dds->standaloneCredits(new StandaloneCredits($eft_credit_array));
    }

    /*
     * This is a test to confirm that the DirectDebitService sets the required parameters we expect for a
     * standaloneCredits call.
     * If a token is specified we expect to validate merchantRefNum, amount, and eft
     */
    public function testCreditEftMissingRequiredFieldsWithToken()
    {
        $this->expectException(PaysafeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Missing required properties: merchantRefNum, amount');

        $dds = new DirectDebitService($this->mock_api_client);
        $eft_credit_array = [
            'eft' => [
                'paymentToken' => 'bogus_payment_token',
            ]
        ];
        $dds->standaloneCredits(new StandaloneCredits($eft_credit_array));
    }

    /*
     * This is a test to confirm that the DirectDebitService sets the required parameters we expect for a
     * standaloneCredits call.
     * If no token is specified, but required Profile params are, we expect to validate merchantRefNum, amount, eft,
     * profile, and billingDetails
     */
    public function testCreditEftMissingRequiredFieldsNoToken()
    {
        $this->expectException(PaysafeException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Missing required properties: merchantRefNum, amount, billingDetails');

        $dds = new DirectDebitService($this->mock_api_client);
        /*
         * Note: we have to at least specify an empty eft or there will be a PHP Error.
         * See https://github.com/paysafegroup/paysafe_sdk_php/issues/13
         */
        $eft_credit_array = [
            'eft' => [ ],
            'profile' => [
                'firstName' => 'firstname',
                'lastName' => 'lastname',
            ],
        ];
        $dds->standaloneCredits(new StandaloneCredits($eft_credit_array));
    }

    /*
     * This is a test to confirm that the DirectDebitService sets expected values for required/optional fields. If a
     * parameter is set in the StandaloneCredits obj, but not in the required or optional lists, it will be omitted from
     * the JSON created by toJson. (toJson is called by processRequest in the api client).
     *
     * So, we'll make our mock api client call toJson, and confirm the output lacks the field that doesn't appear in
     * required/optional.
     *
     * This test omits the token and instead includes profile information. The required/optional lists are built
     * slightly differently in each case
     */
    public function testCreditEftNoTokenInvalidField()
    {
        $this->mock_api_client
            ->expects($this->once())
            ->method('processRequest')
            ->with($this->isInstanceOf(Request::class))
            ->will($this->returnCallback(function (Request $param) {
                return json_decode($param->body->toJson(), true);
            }));
        $dds = new DirectDebitService($this->mock_api_client);

        $eft_credit_array = [
            'id' => 'id is a valid param, but not in required or optional list',
            'eft' => [
                'accountHolderName' => 'accountHolderName',
            ],
            'profile' => [
                'firstName' => 'firstname',
                'lastName' => 'lastname',
            ],
            'merchantRefNum' => 'merchantrefnum',
            'amount' => 555,
            'billingDetails' => [
                'zip' => '10007',
            ],
        ];

        $retval = $dds->standaloneCredits(new StandaloneCredits($eft_credit_array));
        $param_no_id = $eft_credit_array;
        unset($param_no_id['id']);
        $this->assertThat($retval->toJson(), $this->equalTo(json_encode($param_no_id)),
            'Did not receive expected return from DirectDebitService::standaloneCredits');
    }

    /*
     * This is a test to confirm that the DirectDebitService sets expected values for required/optional fields. If a
     * parameter is set in the StandaloneCredits obj, but not in the required or optional lists, it will be omitted from
     * the JSON created by toJson. (toJson is called by processRequest in the api client).
     *
     * So, we'll make our mock api client call toJson, and confirm the output lacks the field that doesn't appear in
     * required/optional.
     *
     * This test includes the token and omits profile information. The required/optional lists are built
     * slightly differently in each case
     */
    public function testCreditEftWithTokenInvalidField()
    {
        $this->mock_api_client
            ->expects($this->once())
            ->method('processRequest')
            ->with($this->isInstanceOf(Request::class))
            ->will($this->returnCallback(function (Request $param) {
                return json_decode($param->body->toJson(), true);
            }));
        $dds = new DirectDebitService($this->mock_api_client);

        $eft_credit_array = [
            'id' => 'id is a valid param, but not in required or optional list',
            'eft' => [
                'paymentToken' => 'myspecialtoken',
            ],
            'merchantRefNum' => 'merchantrefnum',
            'amount' => 555,
        ];

        $retval = $dds->standaloneCredits(new StandaloneCredits($eft_credit_array));
        $param_no_id = $eft_credit_array;
        unset($param_no_id['id']);
        $this->assertThat($retval->toJson(), $this->equalTo(json_encode($param_no_id)),
            'Did not receive expected return from DirectDebitService::standaloneCredits');
    }
}
