<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gallery Access</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #fdfbf7 0%, #f5ebe0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            padding: 60px 50px;
            border-radius: 2px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            max-width: 440px;
            width: 90%;
        }
        h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 300;
            color: #1a1a1a;
            text-align: center;
            margin-bottom: 40px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        input[type="password"] {
            width: 100%;
            padding: 16px 20px;
            border: 1px solid #e8e8e8;
            border-radius: 2px;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        button {
            width: 100%;
            padding: 16px 20px;
            background: linear-gradient(135deg, #d4af37 0%, #c19d2e 100%);
            color: white;
            border: none;
            border-radius: 2px;
            font-size: 0.9rem;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
        }
        .error {
            color: #c9302c;
            font-size: 0.85rem;
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            background: rgba(201,48,44,0.05);
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Private Gallery</h2>
        <form method="post">
            <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
            <button type="submit">Enter Gallery</button>
        </form>
        <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
    </div>
</body>
</html>