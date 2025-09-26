<?php
namespace Mailer;

use database;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class mailer extends database
{
    public $isMailActive;
    public $mailServerHost;
    public $mailServerPort;
    public $website_mail;
    public $website_mail_password;

    public function __construct($email_settings = [])
    {
        if(is_null($this->isMailActive))
        {
           
            $query = self::query("SELECT `value` FROM workgui_settings WHERE setting = 'MailServer' LIMIT 1")->assoc();

            if($query['value'] == '1')
            {
                $this->isMailActive = true;
                $this->mailServerHost = $email_settings['emailServerHost'];
                $this->mailServerPort = $email_settings['emailServerPort'];
                $this->website_mail = $email_settings['website_mail'];
                $this->website_mail_password = $email_settings['website_mail_password'];
            }
            else
                $this->isMailActive = false;
        }
    }

    public function logEmail($sender, $receiver, $message, $subject, $errorlog, $status)
    {  
        self::query("INSERT INTO workgui_email_log (sender, receiver, subject, message, error_message, status) VALUES ('".$sender."', '".$receiver."', '".$subject."', '".$message."', '".$errorlog."', '".$status."')");
    }

    public function sendEmail($email, $subject, $message)
    {
        if(!$this->isMailActive)
        {
            $this->logEmail('offline', $email, $message, $subject, 'Mail server offline', 'disabled');
            return;
        }
        $mail = new PHPMailer();
        $mail->isSMTP(); 
        $mail->Host = $this->mailServerHost; 
        $mail->SMTPAuth = true; 
        $mail->Username = $this->website_mail; // SMTP username
        $mail->Password = $this->website_mail_password;// SMTP password
        $mail->SMTPSecure = 'tls'; 
        $mail->Port = $this->mailServerPort;
        $mail->CharSet = 'UTF-8';
        $mail->From = $this->website_mail;
        $mail->FromName = 'WorkGui';
		$mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->isHTML(true);
        
        if(!$mail->send()) 
            $this->logEmail($this->website_mail, $email, $message, $subject, $mail->ErrorInfo, 'failure');
        else 
            $this->logEmail($this->website_mail, $email, $message, $subject, 'Email was successfully sent', 'success');
    }

}