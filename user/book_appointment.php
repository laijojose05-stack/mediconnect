<?php
require '_init.php';
require_login();

$uid = (int)$_SESSION['user_id'];

/* ---------- Preselect via query string ---------- */
$preDoctorId   = (int)($_GET['doctor_id']   ?? 0);
$preHospitalId = (int)($_GET['hospital_id'] ?? 0);

/* If only hospital given, pick the first doctor from that hospital */
if (!$preDoctorId && $preHospitalId) {
    $st = $conn->prepare("SELECT id FROM doctors WHERE hospital_id = ? ORDER BY doctor_name LIMIT 1");
    if ($st) {
        $st->bind_param("i", $preHospitalId);
        $st->execute();
        $res = $st->get_result();
        $row = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
        $preDoctorId = $row ? (int)$row['id'] : 0;
    }
}

/* ---------- Load doctors ---------- */
$doctors = [];
if ($preHospitalId > 0) {
    $stmt = $conn->prepare(
        "SELECT d.id, d.doctor_name, d.specialization, d.hospital_id, h.hospital_name
         FROM doctors d
         LEFT JOIN hospitals h ON h.id = d.hospital_id
         WHERE d.hospital_id = ?
         ORDER BY d.doctor_name"
    );
    if ($stmt) {
        $stmt->bind_param("i", $preHospitalId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $doctors = $res->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $res = $conn->query(
        "SELECT d.id, d.doctor_name, d.specialization, d.hospital_id, h.hospital_name
         FROM doctors d
         LEFT JOIN hospitals h ON h.id = d.hospital_id
         ORDER BY d.doctor_name"
    );
    if ($res) $doctors = $res->fetch_all(MYSQLI_ASSOC);
}

/* ---------- Hospital context (for banner) ---------- */
$hospitalContext = null;
if ($preHospitalId > 0) {
    $st = $conn->prepare("SELECT hospital_name, city FROM hospitals WHERE id = ?");
    if ($st) {
        $st->bind_param("i", $preHospitalId);
        $st->execute();
        $res = $st->get_result();
        if ($res && $res->num_rows) $hospitalContext = $res->fetch_assoc();
    }
}

/* ---------- Prefill user info ---------- */
$user = current_user($pdo);

/* ---------- Handle submission ---------- */
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $date      = trim($_POST['appointment_date'] ?? '');
    $time      = trim($_POST['appointment_time'] ?? '');
    $pname     = trim($_POST['patient_name']     ?? '');
    $pphone    = trim($_POST['patient_phone']    ?? '');
    $notes     = trim($_POST['notes']            ?? '');

    if (!$doctor_id)                 $err = 'Please select a doctor.';
    elseif ($date === '')            $err = 'Please choose a date.';
    elseif ($time === '')            $err = 'Please choose a time.';
    elseif ($pname === '')           $err = 'Patient name is required.';
    elseif (strtotime($date) === false)  $err = 'Invalid date.';
    elseif (strtotime($date) < strtotime('today'))
                                     $err = 'Appointment date cannot be in the past.';

    $doc = null;
    if (!$err) {
        $st = $conn->prepare("SELECT id, doctor_name, hospital_id FROM doctors WHERE id = ?");
        if ($st) {
            $st->bind_param("i", $doctor_id);
            $st->execute();
            $res = $st->get_result();
            $doc = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
        }
        if (!$doc) $err = 'Selected doctor no longer exists.';
    }

    if (!$err) {
        $st = $conn->prepare(
            "SELECT id FROM appointments
             WHERE user_id = ? AND doctor_id = ?
               AND appointment_date = ? AND appointment_time = ?
               AND status IN ('pending','approved')"
        );
        if ($st) {
            $st->bind_param("iiss", $uid, $doctor_id, $date, $time);
            $st->execute();
            $res = $st->get_result();
            if ($res && $res->num_rows) {
                $err = 'You already have a pending appointment with this doctor at that time.';
            }
        }
    }

    if (!$err && $doc) {
        $st = $conn->prepare(
            "INSERT INTO appointments
                (user_id, hospital_id, doctor_id, patient_name, patient_phone,
                 doctor_name, appointment_date, appointment_time, notes,
                 status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        if ($st) {
            $st->bind_param(
                "iiissssss",
                $uid, $doc['hospital_id'], $doc['id'], $pname, $pphone,
                $doc['doctor_name'], $date, $time, $notes
            );
            if ($st->execute()) {
                $apptId = (int)$conn->insert_id;

                $msg = "Your appointment with {$doc['doctor_name']} on $date at $time is pending confirmation.";
                $st2 = $conn->prepare(
                    "INSERT INTO user_notifications (user_id, title, message, type, related_id)
                     VALUES (?, ?, ?, 'appointment', ?)"
                );
                if ($st2) {
                    $nt = 'Appointment requested';
                    $st2->bind_param("issi", $uid, $nt, $msg, $apptId);
                    $st2->execute();
                }

                $st3 = $conn->prepare(
                    "INSERT INTO hospital_notifications (hospital_id, title, message, type, related_id)
                     VALUES (?, ?, ?, 'appointment', ?)"
                );
                if ($st3) {
                    $hMsg = "A new appointment request from $pname for {$doc['doctor_name']} on $date at $time is pending.";
                    $nt = 'New appointment request';
                    $st3->bind_param("issi", $doc['hospital_id'], $nt, $hMsg, $apptId);
                    $st3->execute();
                }

                flash('success', 'Appointment request submitted successfully!');
                header('Location: appointments.php');
                exit;
            } else {
                $err = 'Could not submit the appointment. Please try again.';
            }
        }
    }

    $preDoctorId = $doctor_id;
}

$pageTitle = 'Book Appointment';
include '_header.php';
?>

<div class="page-head">
  <div>
    <h1>Book an Appointment</h1>
    <p class="subtitle">Fill in the details below and we'll notify you once confirmed.</p>
  </div>
  <a class="btn" href="doctors.php<?= $preHospitalId ? '?hospital_id=' . $preHospitalId : '' ?>">← Back</a>
</div>

<div style="max-width:840px;">

  <!-- Hospital context banner -->
  <?php if ($hospitalContext): ?>
    <div class="alert info">
      🏥 Booking at <strong><?= e($hospitalContext['hospital_name']) ?></strong>
      <?= $hospitalContext['city'] ? '· ' . e($hospitalContext['city']) : '' ?>
      <a href="book_appointment.php" style="margin-left:8px;">Show all doctors →</a>
    </div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="alert danger"><?= e($err) ?></div>
  <?php endif; ?>

  <?php if (!$doctors): ?>
    <div class="card empty">
      <div class="big">👨‍⚕️</div>
      <h3>No doctors available</h3>
      <p>
        <?php if ($hospitalContext): ?>
          This hospital hasn't published any doctors yet.
        <?php else: ?>
          No doctors are registered right now.
        <?php endif; ?>
      </p>
      <?php if ($hospitalContext): ?>
        <a class="btn" href="book_appointment.php">👨‍⚕️ Show All Doctors</a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div class="card">
      <form method="post">
        <!-- Keep hospital context through submission -->
        <?php if ($preHospitalId): ?>
          <input type="hidden" name="hospital_id" value="<?= $preHospitalId ?>">
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label">Doctor</label>
          <select name="doctor_id" class="form-select" required>
            <option value="">Select a doctor…</option>
            <?php foreach ($doctors as $d): ?>
              <option value="<?= (int)$d['id'] ?>"
                <?= $d['id'] == $preDoctorId ? 'selected' : '' ?>>
                <?= e($d['doctor_name']) ?>
                <?= $d['specialization'] ? ' — ' . e($d['specialization']) : '' ?>
                <?php if (!$hospitalContext && $d['hospital_name']): ?>
                  (<?= e($d['hospital_name']) ?>)
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="appointment_date" class="form-control"
                   required min="<?= date('Y-m-d') ?>"
                   value="<?= e($_POST['appointment_date'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Time</label>
            <input type="time" name="appointment_time" class="form-control"
                   required
                   value="<?= e($_POST['appointment_time'] ?? '') ?>">
          </div>
        </div>

        <hr>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Patient Name</label>
            <input name="patient_name" class="form-control" required
                   value="<?= e($_POST['patient_name'] ?? $user['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Patient Phone</label>
            <input name="patient_phone" class="form-control"
                   value="<?= e($_POST['patient_phone'] ?? $user['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Notes <span class="muted">(optional)</span></label>
          <textarea name="notes" class="form-control" rows="3"
                    placeholder="Reason for visit, symptoms, etc."><?= e($_POST['notes'] ?? '') ?></textarea>
        </div>

        <div class="flex">
          <button class="btn primary">📅 Request Appointment</button>
          <a class="btn" href="appointments.php">My Appointments</a>
        </div>

      </form>
    </div>

    <p class="small muted" style="margin-top:14px;">
      Your appointment will be <span class="badge warning">pending</span> until the hospital confirms it.
    </p>

  <?php endif; ?>

</div>

<?php include '_footer.php'; ?>