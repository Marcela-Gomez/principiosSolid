<?php
require_once 'vendor/autoload.php';
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;


class ContactInfo
{
    public $email;
    public $phone;

    public function __construct($email, $phone)
    {
        if ($email !== null){
            $emailValidator = v::email();
            try{
                $emailValidator->assert($email);
            } catch(NestedValidationException $exception){
                die('Invalid email address'. $exception->getMessages());
            }
        }

        if ($phone !== null){
            $phoneValidator = v::phone();
            try{
                $phoneValidator->assert($phone);
            } catch(NestedValidationException $exception){
                die('Invalid phone number'. $exception->getMessages());
            }
        }

        $this->email = $email;
        $this->phone = $phone;
    }
}

class CustomerData {
    public $name;
    public $contactInfo;

    public function __construct($name, $contanctInfoArray){
        $nameValidator = v::stringType()->notEmpty();
        try{
            $nameValidator->assert($name);
        } catch (NestedValidationException $e){
           die('Invalid name'. $e->getMessages());
        }

        $this->name = $name;
        $this->contactInfo = new ContactInfo(
            $contanctInfoArray['email'] ?? null,
            $contanctInfoArray['phone'] ?? null
        );
    }
}

class PaymentData{
    public $amount;
    public $source;

    public function __construct($amount, $source){
        $amountValidator = v::number()->positive();
        try{
            $amountValidator->assert($amount);
        } catch (NestedValidationException $e){
            die('Invalid amount'. $e->getMessages());
        }

        $sourceValidator = v::stringType()->notEmpty();
        try{
            $sourceValidator->assert($source);
        } catch (NestedValidationException $e){
            die('Invalid source'. $e->getMessages());
        }

        $this->amount = $amount;
        $this->source = $source;
    }
}


class CustomerValidator
{
    public function validate(CustomerData $customerData){
        if ($customerData->name == ''){
            die ('Customer name is required');
            return;
        }

        if($customerData->contactInfo == ''){
            die ('Customer contact info is required');
            return;
        }
    }
}

class PaymentDataValidator
{
    public function validate(PaymentData $paymentData){
        if ($paymentData->source == ''){
            die('Payment source is required');
            return;
        }
    }
}
/* 
abstract class Notifier
{
    abstract public function sendConfirmation(CustomerData $customerData);
} */

interface INotifier{
    public function sendConfirmation(CustomerData $customerData);
}

class emailNotifier implements INotifier{
    public function sendConfirmation(CustomerData $customerData){
        $email = $customerData->contactInfo->email;
        $subject = 'Payment Successful';
        $message = 'Your payment was successful';
        echo'email sent '. $customerData->contactInfo->email;
    }
}

class SMSNotifier implements INotifier{
    private $smsGateway;

    public function __construct($smsGateway = 'Movistar'){
        $this->smsGateway = $smsGateway;
    }

    public function sendConfirmation(CustomerData $customerData){
        $phone = $customerData->contactInfo->phone;
        $message = 'Your payment was successful';
        $sms_gateway = $this->smsGateway;
        echo 'SMS sent'. $customerData->contactInfo->phone;
}
}

class TransactionLogger{
    public function logTransaction($charge, CustomerData $customerData){
        $transactionDetails = 'Transaction ID: '. $charge->id. ' Amount: '. $charge->amount. ' Status: '. $charge->status . ' Customer Name: '. $customerData->name. PHP_EOL;
        file_put_contents('transaction.txt', $transactionDetails, FILE_APPEND);
    }
}
/* 
abstract class PaymentProcessor{
    abstract public function processPayment(CustomerData $customerData,PaymentData $paymentData);
} */

interface   IPaymentProcessor{
    public function processPayment(CustomerData $customerData, PaymentData $paymentData);
}

interface IRefundProcessor{
    public function processRefund($transactionId, $amount);
}

class StripePaymentProcessor implements IPaymentProcessor, IRefundProcessor{

    public function processPayment(CustomerData $customerData, PaymentData $paymentData){
        $stripe = new \Stripe\StripeClient('sk_test_51QOtVzIbpbJ9zG6ZgpR0aMyUl4xjEkeTEU0qfL2lnYkWlPcynbv5Qs9T5RwRD3uvHDGMOnhSm69jNSwl39Bs27kh00FSX2KhVs');

        try{
            $charge = $stripe->charges->create([
                'amount' => $paymentData->amount,
                'currency' => 'usd',
                'source' => $paymentData->source,
                'description' => 'Testing solid principles',
            ]);
            return $charge;
        }
        catch (Exception $e){
            echo 'Payment failed' . $e->getMessage();
            return;
        }  
    }

    public function processRefund($transactionId, $amount){
        $stripe = new \Stripe\StripeClient('sk_test_51QOtVzIbpbJ9zG6ZgpR0aMyUl4xjEkeTEU0qfL2lnYkWlPcynbv5Qs9T5RwRD3uvHDGMOnhSm69jNSwl39Bs27kh00FSX2KhVs');

        try{
            $charge = $stripe->refunds->create([
                'charge' => $transactionId,
                'amount' => $amount,
            ]);
            return $refund;
        }
        catch (Exception $e){
            echo 'Refund failed' . $e->getMessage();
            return;
        }  
    }

}

class PaymentService{
    private $customerValidator;
    private $paymentDataValidator;
    private INotifier $notifier;
    private $transactionLogger;
    private IPaymentProcessor $paymentProcessor;
    private IRefundProcessor $refundProcessor;

    public function __construct(INotifier $notifier,IrefundProcessor $refundProcessor)
    {
        $this->customerValidator = new CustomerValidator();
        $this->paymentDataValidator = new PaymentDataValidator();
        $this->notifier = $notifier;
        $this->transactionLogger = new TransactionLogger();
        $this->paymentProcessor = new StripePaymentProcessor();
        $this->refundProcessor = $refundProcessor;
    }

    public function processTransaction($customerDataArray, $paymentDataArray){
        $customerData = new CustomerData($customerDataArray['name'], $customerDataArray['contact_info']);
        $paymentData = new PaymentData($paymentDataArray['amount'], $paymentDataArray['source']);

        try{
            $this->customerValidator->validate($customerData);
            $this->paymentDataValidator->validate($paymentData);
        }
        catch (Exception $e){
            echo 'Validation failed' . $e->getMessage();
            return;
        }

        $charge = $this->paymentProcessor->processPayment($customerData, $paymentData);

        if($charge !== null){
            $this->notifier->sendConfirmation($customerData);
            $this->transactionLogger->logTransaction($charge, $customerData);
        }
    }

    public function processRefund($transactionId, $amount, $customerData)
    {
        try{
            $refund = $this->refundProcessor->processRefund($transactionId, $amount);
            if($refund !== null){
                $this->notifier->sendRefundConfirmation($customerData);
                $this->transactionLogger->logTransaction($refund, $customerData);
            }
        }catch (Exception $e){
            echo 'Refund failed' .e->getMessage();
            return;
        }
    }
}

$paymentWithEmail = [
    'name' => 'User 1',
    'contact_info' => [
        'email' => 'testing123@gmil.com'
    ]
];

$paymentWithPhone = [
    'name' => 'User 2',
    'contact_info' => [
        'phone' => '+1 650 253 00 00'
    ]
];

$paymentData = [
    'amount' => 2000,
    'source' => 'tok_visa'
];

$smsnotifier = new SMSNotifier('Tigo');
$emailNotifier = new EmailNotifier();

$stripeProcessor = new StripePaymentProcessor();

$processor = new PaymentService($smsnotifier);
$processor->processTransaction($paymentWithEmail, $paymentData);
$processor->processRefund('ch_3QPwlSIbpbJ9zG6Z09iDbjO0', 200, $paymentWithEmail)
?>