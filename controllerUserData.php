<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require "connection.php";
$email = "";
$name = "";
$errors = array();
function getEnvVariableDirect($key, $default = null) {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        error_log("❌ .env file not found at: $envFile");
        return $default;
    }
    
    $content = file_get_contents($envFile);
    preg_match('/' . $key . '=(.*)/', $content, $matches);
    
    if (isset($matches[1])) {
        $value = trim($matches[1]);
        // Remove quotes if present
        $value = trim($value, '"\'');
        error_log("✅ Found $key: " . substr($value, 0, 10) . "...");
        return $value;
    }
    
    error_log("❌ $key not found in .env file");
    return $default;
}
$brevo_api_key = getEnvVariableDirect('BREVO_API_KEY');
$brevo_sender_email = getEnvVariableDirect('BREVO_SENDER_EMAIL', 'nellurujaswanth2004@gmail.com');
$brevo_sender_name = getEnvVariableDirect('BREVO_SENDER_NAME', 'AI Agent System');

// Debug output (remove this after testing)
error_log("=== BREVO CONFIG DEBUG ===");
error_log("API Key: " . ($brevo_api_key ? substr($brevo_api_key, 0, 10) . "..." : "NOT FOUND"));
error_log("Sender Email: $brevo_sender_email");
error_log("Sender Name: $brevo_sender_name");
error_log("===========================");


// Updated sendEmailBrevo function with better error handling
function sendEmailBrevo($to, $subject, $htmlContent, $api_key, $sender_email, $sender_name) {
    $data = array(
        "sender" => array(
            "name" => $sender_name,
            "email" => $sender_email
        ),
        "to" => array(
            array(
                "email" => $to,
                "name" => "User"
            )
        ),
        "subject" => $subject,
        "htmlContent" => $htmlContent
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'accept: application/json',
        'api-key: ' . $api_key,
        'content-type: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Debug logging (you can remove this after testing)
    error_log("Brevo API Response - Code: $http_code, Error: $curl_error, Result: $result");
    
    return $http_code === 201;
}

// Function to generate beautiful OTP email template
function getOTPEmailTemplate($otp_code, $type = 'verification') {
    if ($type === 'reset') {
        $title = "Password Reset Code";
        $message = "Your password reset code for AI Agent System";
    } else {
        $title = "Email Verification Code";
        $message = "Your verification code for AI Agent System";
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #0c0c17; color: #fff; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: rgba(12, 12, 23, 0.9); border: 1px solid #00ff9d; border-radius: 10px; padding: 30px; }
            .header { text-align: center; margin-bottom: 30px; }
            .logo { font-size: 2.5rem; color: #00ff9d; margin-bottom: 10px; }
            .title { font-size: 1.5rem; color: #00ff9d; margin-bottom: 10px; }
            .otp-code { background: rgba(0, 255, 157, 0.1); border: 2px solid #00ff9d; border-radius: 8px; padding: 20px; text-align: center; font-size: 2rem; font-weight: bold; letter-spacing: 5px; margin: 20px 0; color: #00ff9d; }
            .footer { margin-top: 30px; text-align: center; font-size: 0.8rem; color: #888; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">🤖 AI AGENT SYSTEM</div>
                <h1 class="title">' . $title . '</h1>
            </div>
            <p>Hello,</p>
            <p>' . $message . '</p>
            <div class="otp-code">' . $otp_code . '</div>
            <p>This code will expire in 10 minutes. Please do not share this code with anyone.</p>
            <div class="footer">
                <p>If you didn\'t request this code, please ignore this email.</p>
                <p>&copy; 2024 AI Agent System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
}

//if user signup button
//if user signup button
if(isset($_POST['signup'])){
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = mysqli_real_escape_string($con, $_POST['password']);
    $cpassword = mysqli_real_escape_string($con, $_POST['cpassword']);
    
    if($password !== $cpassword){
        $errors['password'] = "Confirm password not matched!";
    }
    
    $email_check = "SELECT * FROM usertable WHERE email = '$email'";
    $res = mysqli_query($con, $email_check);
    if(mysqli_num_rows($res) > 0){
        $errors['email'] = "Email that you have entered is already exist!";
    }
    
    if(count($errors) === 0){
        $encpass = password_hash($password, PASSWORD_BCRYPT);
        $code = rand(999999, 111111);
        $status = "notverified";
        $insert_data = "INSERT INTO usertable (name, email, password, code, status)
                        values('$name', '$email', '$encpass', '$code', '$status')";
        $data_check = mysqli_query($con, $insert_data);
        
        if($data_check){
            $subject = "AI Agent System - Email Verification Code";
            $message = getOTPEmailTemplate($code, 'verification');
            
            // Send email using Brevo
            if(sendEmailBrevo($email, $subject, $message, $brevo_api_key, $brevo_sender_email, $brevo_sender_name)){
                $info = "We've sent a verification code to your email - $email";
                $_SESSION['info'] = $info;
                $_SESSION['email'] = $email;
                $_SESSION['password'] = $password;
                header('location: user-otp.php');
                exit();
            } else {
                // Delete the user record if email sending fails
                mysqli_query($con, "DELETE FROM usertable WHERE email = '$email'");
                $errors['otp-error'] = "Failed while sending code via Brevo! Please try again.";
            }
        } else {
            $errors['db-error'] = "Failed while inserting data into database: " . mysqli_error($con);
        }
    }
}

//if user click verification code submit button
if(isset($_POST['check'])){
    $_SESSION['info'] = "";
    $otp_code = mysqli_real_escape_string($con, $_POST['otp']);
    $check_code = "SELECT * FROM usertable WHERE code = $otp_code";
    $code_res = mysqli_query($con, $check_code);
    if(mysqli_num_rows($code_res) > 0){
        $fetch_data = mysqli_fetch_assoc($code_res);
        $fetch_code = $fetch_data['code'];
        $email = $fetch_data['email'];
        $code = 0;
        $status = 'verified';
        $update_otp = "UPDATE usertable SET code = $code, status = '$status' WHERE code = $fetch_code";
        $update_res = mysqli_query($con, $update_otp);
        if($update_res){
            $_SESSION['name'] = $fetch_data['name'];
            $_SESSION['email'] = $email;
            header('location: home.php');
            exit();
        }else{
            $errors['otp-error'] = "Failed while updating code!";
        }
    }else{
        $errors['otp-error'] = "You've entered incorrect code!";
    }
}

//if user click login button
if(isset($_POST['login'])){
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = mysqli_real_escape_string($con, $_POST['password']);
    $check_email = "SELECT * FROM usertable WHERE email = '$email'";
    $res = mysqli_query($con, $check_email);
    if(mysqli_num_rows($res) > 0){
        $fetch = mysqli_fetch_assoc($res);
        $fetch_pass = $fetch['password'];
        if(password_verify($password, $fetch_pass)){
            $_SESSION['email'] = $email;
            $status = $fetch['status'];
            if($status == 'verified'){
              $_SESSION['email'] = $email;
              $_SESSION['password'] = $password;
              $_SESSION['name'] = $fetch['name'];
                header('location: home.php');
            }else{
                $info = "It's look like you haven't still verify your email - $email";
                $_SESSION['info'] = $info;
                header('location: user-otp.php');
            }
        }else{
            $errors['email'] = "Incorrect email or password!";
        }
    }else{
        $errors['email'] = "It's look like you're not yet a member! Click on the bottom link to signup.";
    }
}

//if user click continue button in forgot password form
if(isset($_POST['check-email'])){
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $check_email = "SELECT * FROM usertable WHERE email='$email'";
    $run_sql = mysqli_query($con, $check_email);
    if(mysqli_num_rows($run_sql) > 0){
        $code = rand(999999, 111111);
        $insert_code = "UPDATE usertable SET code = $code WHERE email = '$email'";
        $run_query =  mysqli_query($con, $insert_code);
        if($run_query){
            $subject = "AI Agent System - Password Reset Code";
            $message = getOTPEmailTemplate($code, 'reset');
            
            // Send email using Brevo
            if(sendEmailBrevo($email, $subject, $message, $brevo_api_key, $brevo_sender_email, $brevo_sender_name)){
                $info = "We've sent a password reset OTP to your email - $email";
                $_SESSION['info'] = $info;
                $_SESSION['email'] = $email;
                header('location: reset-code.php');
                exit();
            }else{
                $errors['otp-error'] = "Failed while sending code via Brevo! Please try again.";
            }
        }else{
            $errors['db-error'] = "Something went wrong!";
        }
    }else{
        $errors['email'] = "This email address does not exist!";
    }
}

//if user click check reset otp button
if(isset($_POST['check-reset-otp'])){
    $_SESSION['info'] = "";
    $otp_code = mysqli_real_escape_string($con, $_POST['otp']);
    $check_code = "SELECT * FROM usertable WHERE code = $otp_code";
    $code_res = mysqli_query($con, $check_code);
    if(mysqli_num_rows($code_res) > 0){
        $fetch_data = mysqli_fetch_assoc($code_res);
        $email = $fetch_data['email'];
        $_SESSION['email'] = $email;
        $info = "Please create a new password that you don't use on any other site.";
        $_SESSION['info'] = $info;
        header('location: new-password.php');
        exit();
    }else{
        $errors['otp-error'] = "You've entered incorrect code!";
    }
}

//if user click change password button
if(isset($_POST['change-password'])){
    $_SESSION['info'] = "";
    $password = mysqli_real_escape_string($con, $_POST['password']);
    $cpassword = mysqli_real_escape_string($con, $_POST['cpassword']);
    if($password !== $cpassword){
        $errors['password'] = "Confirm password not matched!";
    }else{
        $code = 0;
        $email = $_SESSION['email']; //getting this email using session
        $encpass = password_hash($password, PASSWORD_BCRYPT);
        $update_pass = "UPDATE usertable SET code = $code, password = '$encpass' WHERE email = '$email'";
        $run_query = mysqli_query($con, $update_pass);
        if($run_query){
            $info = "Your password changed. Now you can login with your new password.";
            $_SESSION['info'] = $info;
            header('Location: password-changed.php');
        }else{
            $errors['db-error'] = "Failed to change your password!";
        }
    }
}

//if login now button click
if(isset($_POST['login-now'])){
    header('Location: login-user.php');
}
?>
