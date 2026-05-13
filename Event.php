<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="Event.css">
</head>
<body>
<div class="container">
    <h1>Book Event</h1>
    <form id="bookingForm">
        <label for="bookingId">Booking ID</label>
        <input type="text" id="bookingId" name="bookingId" required>

        <label for="equipment">Equipment</label>
        <select id="equipment" name="equipment">
            <option value="">--Select--</option>
            <option value="Sound System">Sound System</option>
            <option value="Lighting">Lighting</option>
            <option value="Projector">Projector</option>
        </select>

        <label for="eventType">Event Type</label>
        <select id="eventType" name="eventType">
            <option value="">--Select--</option>
            <option value="Wedding">Wedding</option>
            <option value="Birthday">Birthday</option>
            <option value="Conference">Conference</option>
        </select>

        

        <label for="place">Place</label>
        <input type="text" id="place" name="place" required>

        <label for="food">Food</label>
        <select id="food" name="food">
            <option value="">--Select--</option>
            <option value="Buffet">Buffet</option>
            <option value="Plated">Plated</option>
        </select>

         <!-- <label for="foodType">Food </label> -->
        <li><a href="Food.php">Food Calculater</a></li>
        <!-- <select id="foodType" name="foodType">
           <option value="">--Select--</option>
            <option value="Vegetarian">Vegetarian</option>
            <option value="Non-Vegetarian">Non-Vegetarian</option>
        </select> -->

        <label for="noOfGuests">No. of Guests</label>
        <input type="number" id="noOfGuests" name="noOfGuests" min="1" required>
   
</body>
</html>