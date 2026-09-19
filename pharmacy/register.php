<?php
session_start();
require_once "../config/database.php";

if (isset($_SESSION["pharmacy_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name    = trim($_POST["pharmacy_name"] ?? "");
    $email   = trim($_POST["email"] ?? "");
    $phone   = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $city    = trim($_POST["city"] ?? "");
    $license = trim($_POST["license_number"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";

    if ($name === "" || $email === "" || $license === "" || $password === "") {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM pharmacies WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "That email is already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO pharmacies
                 (pharmacy_name, email, phone, address, city, license_number, password, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
            );
            $stmt->bind_param("sssssss", $name, $email, $phone, $address, $city, $license, $hash);

            if ($stmt->execute()) {
                $newId = (int)$stmt->insert_id;
                $n = $conn->prepare(
                    "INSERT INTO admin_notifications (admin_id, title, message, type, related_id)
                     VALUES (1, 'New pharmacy pending approval', 'Pharmacy \"$name\" ($email) registered and is awaiting approval.', 'registration', ?)"
                );
                if ($n) {
                    $n->bind_param("i", $newId);
                    $n->execute();
                }

                header("Location: login.php?success=" . urlencode("Registration submitted. Await admin approval."));
                exit();
            } else {
                $error = "Registration failed: " . $stmt->error;
            }

            $check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pharmacy Registration | MediConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;}
body{background:#0f1319;color:#ffffff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}

.app-card{width:100%;max-width:1050px;min-height:740px;background:#171c26;border-radius:24px;display:flex;position:relative;overflow:hidden;border:1px solid #262d3d;box-shadow:0 25px 50px -12px rgba(0,0,0,.5);}

.navbar{position:absolute;top:0;left:0;width:100%;padding:32px 48px;display:flex;align-items:center;justify-content:space-between;z-index:10;}
.nav-brand{display:flex;align-items:center;gap:10px;color:#ffffff;text-decoration:none;font-size:1.15rem;font-weight:700;}
.brand-icon{width:28px;height:28px;background:transparent;border-radius:50%;display:flex;align-items:center;justify-content:center;}
.dot-blue{color:#1d8cf8;}
.nav-links{display:flex;gap:32px;}
.nav-links a{color:#8b95a5;text-decoration:none;font-size:.95rem;font-weight:500;}
.nav-links a:hover{color:#ffffff;}

.form-section{flex:1.3;padding:120px 48px 48px;display:flex;flex-direction:column;justify-content:center;z-index:5;}
.main-title{font-size:2rem;font-weight:700;margin-bottom:6px;letter-spacing:-.5px;}
.tagline{font-size:1.05rem;font-weight:600;color:#1d8cf8;margin-bottom:8px;}
.description{font-size:.85rem;color:#8b95a5;line-height:1.5;margin-bottom:22px;max-width:460px;}

.message{padding:13px 15px;border-radius:10px;margin-bottom:18px;font-size:13px;}
.error{background:#3b1d25;color:#ff9eaa;border:1px solid #5c2733;}

.form-container{display:flex;flex-direction:column;gap:12px;margin-bottom:18px;}
.input-box{position:relative;background:#212836;border-radius:14px;padding:9px 16px;border:1.5px solid transparent;transition:.2s;}
.input-box:focus-within{border-color:#1d8cf8;box-shadow:0 0 0 1px #1d8cf8;}
.input-box label{display:block;font-size:.68rem;color:#6c788a;margin-bottom:3px;font-weight:600;letter-spacing:.5px;}
.input-box input{width:100%;background:transparent;border:none;outline:none;color:#ffffff;font-size:.9rem;font-weight:500;padding-right:28px;}
.input-box input::placeholder{color:#596474;}

.row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}

.submit-btn{width:100%;background:#1d8cf8;color:#ffffff;border:none;border-radius:14px;padding:14px 20px;font-size:.95rem;font-weight:600;cursor:pointer;box-shadow:0 8px 20px rgba(29,140,248,.3);transition:.2s;}
.submit-btn:hover{background:#1572cd;transform:translateY(-1px);}

.signup-text{margin-top:16px;font-size:.85rem;color:#8b95a5;}
.signup-text a{color:#1d8cf8;text-decoration:none;font-weight:600;}

.info-note{margin-top:12px;padding:10px 14px;background:rgba(29,140,248,.08);border:1px solid rgba(29,140,248,.25);border-radius:10px;font-size:.78rem;color:#7da9d9;}

.bg-section{flex:1;position:relative;background:linear-gradient(135deg,rgba(23,28,38,.3),rgba(15,19,25,.85)),url('https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=1000&q=80') center/cover no-repeat;display:flex;align-items:flex-end;justify-content:flex-end;padding:48px;}
.bg-section::before{content:'';position:absolute;top:-10%;left:-80px;width:160px;height:120%;border-left:1px dashed rgba(255,255,255,.15);border-radius:50%;}
.bottom-logo{color:#ffffff;font-size:2rem;font-weight:800;opacity:.8;}

@media(max-width:850px){
  .app-card{flex-direction:column;}
  .form-section{width:100%;padding:110px 35px 40px;}
  .bg-section{min-height:180px;width:100%;}
  .row-2,.row-3{grid-template-columns:1fr;}
}
@media(max-width:500px){
  .navbar{padding:25px;}
  .nav-links{gap:15px;}
  .form-section{padding-left:25px;padding-right:25px;}
  .main-title{font-size:1.75rem;}
}
</style>
<?php require_once "../partials/_theme.php"; ?>
</head>
<body>

<div class="app-card">

  <header class="navbar">
    <a href="#" class="nav-brand">
      <span class="brand-icon"><img src="../assets/logo.png" alt="MediConnect" style="width:100%;height:100%;object-fit:contain;"></span>
      MediConnect<span class="dot-blue">.</span>
    </a>
    <div class="nav-links">
      <a href="../index.php">Home</a>
      <a href="#">Contact</a>
    </div>
  </header>

  <main class="form-section">

    <h1 class="main-title">Register Pharmacy<span class="dot-blue">.</span></h1>
    <h2 class="tagline">Join Our Network</h2>
    <p class="description">
      Register your pharmacy to receive medicine requests from
      patients and manage your stock — all in one portal.
    </p>

    <?php if (!empty($error)): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST" autocomplete="off">
      <div class="form-container">

        <div class="row-2">
          <div class="input-box">
            <label for="pharmacy_name">PHARMACY NAME *</label>
            <input type="text" id="pharmacy_name" name="pharmacy_name"
                   placeholder="City Care Pharmacy"
                   value="<?= htmlspecialchars($_POST["pharmacy_name"] ?? "") ?>"
                   required>
          </div>

          <div class="input-box">
            <label for="license_number">LICENSE NUMBER *</label>
            <input type="text" id="license_number" name="license_number"
                   placeholder="LIC-XXXX"
                   value="<?= htmlspecialchars($_POST["license_number"] ?? "") ?>"
                   required>
          </div>
        </div>

        <div class="row-2">
          <div class="input-box">
            <label for="email">EMAIL *</label>
            <input type="email" id="email" name="email"
                   placeholder="pharmacy@example.com"
                   value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                   required>
          </div>

          <div class="input-box">
            <label for="phone">PHONE</label>
            <input type="text" id="phone" name="phone"
                   placeholder="0300-1234567"
                   value="<?= htmlspecialchars($_POST["phone"] ?? "") ?>">
          </div>
        </div>

        <div class="row-3">
          <div class="input-box" style="grid-column:span 2;">
            <label for="address">ADDRESS</label>
            <input type="text" id="address" name="address"
                   placeholder="Street, Area"
                   value="<?= htmlspecialchars($_POST["address"] ?? "") ?>">
          </div>

          <div class="input-box">
            <label for="city">CITY</label>
            <input type="text" id="city" name="city"
                   placeholder="Karachi"
                   value="<?= htmlspecialchars($_POST["city"] ?? "") ?>">
          </div>
        </div>

        <div class="row-2">
          <div class="input-box">
            <label for="password">PASSWORD *</label>
            <input type="password" id="password" name="password"
                   placeholder="Min 6 chars"
                   required>
          </div>

          <div class="input-box">
            <label for="confirm">CONFIRM PASSWORD *</label>
            <input type="password" id="confirm" name="confirm"
                   placeholder="Repeat password"
                   required>
          </div>
        </div>

      </div>

      <button type="submit" class="submit-btn">Submit Registration</button>

      <div class="info-note">
        ℹ️ Your pharmacy will be reviewed by an admin. You'll be able to log in
        once your account is approved.
      </div>
    </form>

    <p class="signup-text">
      Already have an account?
      <a href="login.php">Login</a>
    </p>

  </main>

  <div class="bg-section">
    <div class="bottom-logo"><img src="../assets/logo.png" alt="MediConnect" style="width:96px;height:auto;opacity:.9;"></div>
  </div>

</div>

</body>
</html>