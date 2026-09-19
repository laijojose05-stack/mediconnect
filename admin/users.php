<?php
session_start();
require_once "../config/database.php";

if (empty($_SESSION["user"]) || ($_SESSION["user"]["role"] ?? "") !== "admin") {
    header("Location: login.php");
    exit;
}

require_once "../partials/_right.php";
$rightCfg = [
    "ntable"             => "admin_notifications",
    "recipient_col"      => "admin_id",
    "recipient_id"       => (int)($_SESSION["user"]["id"] ?? 1),
    "profile_table"      => "admins",
    "profile_id_field"   => "id",
    "profile_label_field"=> "name",
    "profile_email_field"=> "email",
    "session_label_key"  => "name",
    "profile_title"      => "Admin Profile",
    "profile_fields"     => [
        ["name" => "name",  "label" => "Name"],
    ],
];
right_handle($conn, $rightCfg);
$rightUnread = right_unread($conn, $rightCfg);

$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $stmt = $conn->prepare(
        "SELECT id, name, email, created_at FROM users
         WHERE name LIKE ? OR email LIKE ?
         ORDER BY id DESC"
    );
    $term = "%$search%";
    $stmt->bind_param("ss", $term, $term);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query(
        "SELECT id, name, email, created_at FROM users ORDER BY id DESC"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users | MediConnect Admin</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif}
body{background:#0f1319;color:#fff}
.sidebar{position:fixed;width:260px;height:100vh;background:#121720;border-right:1px solid #262d3d;padding:28px 18px;display:flex;flex-direction:column}
.logo{font-size:20px;font-weight:700;margin-bottom:45px}.logo span{color:#1d8cf8}
.menu{display:flex;flex-direction:column;gap:7px}
.menu a{padding:13px 15px;border-radius:10px;color:#8b95a5;text-decoration:none;font-size:14px}
.menu a:hover,.menu a.active{background:rgba(29,140,248,.12);color:#1d8cf8}
.logout{margin-top:auto}.logout a{display:block;text-align:center;padding:13px;background:rgba(239,68,68,.08);color:#ff6b6b;border-radius:10px;text-decoration:none}
.main{margin-left:260px;padding:35px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px}
.header h1{font-size:28px}.header p{color:#8b95a5;font-size:14px;margin-top:6px}
.card{background:#171c26;border:1px solid #262d3d;border-radius:18px;padding:25px}
.toolbar{display:flex;justify-content:space-between;gap:15px;margin-bottom:25px}
.search-box{display:flex;width:350px;max-width:100%}
.search-box input{flex:1;background:#212836;border:1px solid #262d3d;color:#fff;padding:13px;border-radius:10px 0 0 10px;outline:none}
.search-box button{background:#1d8cf8;color:#fff;border:none;padding:0 20px;border-radius:0 10px 10px 0;cursor:pointer}
table{width:100%;border-collapse:collapse}
th{color:#8b95a5;font-size:12px;text-align:left;padding:15px;border-bottom:1px solid #262d3d}
td{padding:17px 15px;border-bottom:1px solid #262d3d;font-size:14px}
tr:hover{background:rgba(255,255,255,.02)}
.badge{background:rgba(34,197,94,.12);color:#4ade80;padding:6px 10px;border-radius:20px;font-size:11px}
@media(max-width:750px){.sidebar{width:70px;padding:20px 8px}.logo{font-size:0}.logo:after{content:'+';font-size:25px;color:#1d8cf8}.menu a{font-size:0}.main{margin-left:70px;padding:20px}.toolbar{flex-direction:column}.search-box{width:100%}.table-wrap{overflow-x:auto}}

.bname{font-size:17px;white-space:nowrap;}@media(max-width:768px){.bname{font-size:0 !important;}}
</style>
<?php require_once "../partials/_theme.php"; ?>
</head>

<body>

<div class="sidebar">
    <div class="logo" style="display:flex;align-items:center;gap:10px;"><img src="../assets/logo.png" alt="MediConnect" style="height:48px;width:auto;max-width:100%;"><span class="bname" style="color:#f5f7fa;">MediConnect<span style="color:#1d8cf8;">.</span></span></div>

    <div class="menu">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="users.php" class="active">👥 Users</a>
        <a href="hospitals.php">🏥 Hospitals</a>
        <a href="pharmacies.php">💊 Pharmacies</a>
        <a href="medicines.php">💉 Medicines</a>
        <a href="reports.php">📈 Reports</a>
    </div>

    <div class="logout">
        <a href="../logout.php">🚪 Logout</a>
    </div>
</div>

<div class="main">

    <div class="header">
        <div>
            <h1>Users</h1>
            <p>Manage registered MediConnect users.</p>
        </div>
        <?php echo right_buttons($rightUnread, $rightCfg["profile_title"]); ?>
    </div>

    <div class="card">

        <div class="toolbar">

            <form method="GET" class="search-box">
                <input
                    type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search users..."
                >
                <button type="submit">Search</button>
            </form>

        </div>

        <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>ID</th>
                    <th>USER</th>
                    <th>EMAIL</th>
                    <th>STATUS</th>
                    <th>REGISTERED</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($users && $users->num_rows > 0): ?>

                <?php while ($user = $users->fetch_assoc()): ?>

                <tr>
                    <td>#<?php echo $user["id"]; ?></td>
                    <td><?php echo htmlspecialchars($user["name"]); ?></td>
                    <td><?php echo htmlspecialchars($user["email"]); ?></td>
                    <td><span class="badge">Active</span></td>
                    <td>
                        <?php
                        echo !empty($user["created_at"])
                            ? date("d M Y", strtotime($user["created_at"]))
                            : "-";
                        ?>
                    </td>
                </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" style="text-align:center;color:#8b95a5;padding:30px;">
                        No users found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

        </div>

    </div>

</div>

<script>
document.querySelectorAll("tr").forEach(row => {
    row.addEventListener("click", function() {
        this.style.transition = "0.2s";
    });
});
</script>
<?php echo right_panels($conn, $rightCfg, $rightUnread); ?>

</body>
</html>