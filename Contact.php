
<?php
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link rel="stylesheet" href="Contact.css">
    <link rel="stylesheet" href="navbar.css">


</head>
<body>
    <div class="container">
        <form action="sendemail.php" method="post">
        <div class="contact">
            <h6>Contact Information</h6>
            <div class="box">
                <div class="text">
                    <h2>Address</h2>
                    <p>12 <br> Street Name <br> City, Country</p>
                </div>
            </div>
            <div class="box">
                <div class="text">
                    <h2>Phone Number</h2>
                    <p>011-11111111</p>
                </div>
            </div>
            <div class="box">
                <div class="text">
                    <h2>Email Address</h2>
                    <p>your-email@example.com</p>
                </div>
            </div>
        </div>
        </form>
        <form action="" method="post">
            <h1>Contact Us Form</h1>
            <?php
            if (isset($error)) {
                echo "<p style='color:red;'>$error</p>";
            }
            if (isset($success)) {
                echo "<p style='color:green;'>$success</p>";
            }
            ?>
            <input type="text" name="firstname" placeholder="First Name" required>
            <input type="text" name="lastname" placeholder="Last Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="phone" placeholder="Phone Number" required>
            <h4>Type your message here...</h4>
            <textarea name="message" required></textarea>
            <input type="submit" value="Send" id="button">
        </form>
    </div>
</body>
</html>
