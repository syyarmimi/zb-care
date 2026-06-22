<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>ZB-CARE Specialist Hospital</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    background:#f4f8fc;
    font-family:'Segoe UI', sans-serif;
    overflow-x:hidden;
    color:#0f172a;
}

/* =========================
   NAVBAR
========================= */

.navbar{
    background:white;
    padding:18px 0;
    box-shadow:0 2px 15px rgba(0,0,0,0.05);
}

.navbar-brand{
    font-size:30px;
    font-weight:800;
    color:#0d6efd !important;
}

.nav-link{
    color:#334155 !important;
    font-weight:500;
    margin-left:20px;
}

.nav-link:hover{
    color:#0d6efd !important;
}

.staff-btn{
    background:#0d6efd;
    color:white;
    border:none;
    padding:12px 28px;
    border-radius:40px;
    font-weight:600;
    transition:0.3s;
    text-decoration:none;
}

.staff-btn:hover{
    background:#2563eb;
    color:white;
}

/* =========================
   HERO
========================= */

.hero{
    position:relative;
    height:90vh;
    background:
    linear-gradient(rgba(15,23,42,0.65), rgba(15,23,42,0.65)),
    url('https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?q=80&w=1600&auto=format&fit=crop');

    background-size:cover;
    background-position:center;

    display:flex;
    align-items:center;
}

.hero-content{
    color:white;
}

.hero-sub{
    background:rgba(255,255,255,0.15);
    display:inline-block;
    padding:10px 18px;
    border-radius:50px;
    margin-bottom:25px;
    backdrop-filter:blur(5px);
}

.hero-title{
    font-size:65px;
    font-weight:800;
    line-height:1.2;
}

.hero-title span{
    color:#60a5fa;
}

.hero-text{
    margin-top:25px;
    font-size:20px;
    line-height:1.8;
    color:#e2e8f0;
    max-width:700px;
}

.hero-buttons{
    margin-top:40px;
}

.hero-buttons .btn{
    padding:15px 35px;
    border-radius:50px;
    font-weight:600;
    font-size:17px;
}

.btn-book{
    background:#0d6efd;
    color:white;
    border:none;
}

.btn-book:hover{
    background:#2563eb;
}

.btn-staff{
    border:2px solid white;
    color:white;
}

.btn-staff:hover{
    background:white;
    color:#0f172a;
}

/* =========================
   SEARCH BOX
========================= */

.search-box{
    background:white;
    padding:30px;
    border-radius:25px;
    margin-top:-70px;
    position:relative;
    z-index:100;
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

.search-box input,
.search-box select{
    height:55px;
    border-radius:12px;
}

/* =========================
   SECTION
========================= */

.section{
    padding:100px 0;
}

.section-title{
    font-size:48px;
    font-weight:800;
    margin-bottom:20px;
}

.section-text{
    color:#64748b;
    font-size:18px;
    line-height:1.9;
}

/* =========================
   SERVICE CARD
========================= */

.service-card{
    background:white;
    border-radius:25px;
    padding:40px;
    height:100%;
    transition:0.3s;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
}

.service-card:hover{
    transform:translateY(-10px);
}

.service-icon{
    width:90px;
    height:90px;
    border-radius:20px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:40px;
    margin-bottom:25px;
}

.service-card h4{
    font-weight:700;
}

/* =========================
   IMAGE SECTION
========================= */

.info-img{
    width:100%;
    border-radius:30px;
    object-fit:cover;
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

/* =========================
   SPECIALIST CARD
========================= */

.department-card{
    background:white;
    border-radius:25px;
    padding:35px;
    text-align:center;
    transition:0.3s;
    box-shadow:0 5px 20px rgba(0,0,0,0.05);
    height:100%;
}

.department-card:hover{
    transform:translateY(-10px);
}

.department-icon{
    font-size:55px;
    margin-bottom:20px;
}

/* =========================
   APPOINTMENT CTA
========================= */

.cta-box{
    background:#0d6efd;
    color:white;
    padding:70px;
    border-radius:35px;
    text-align:center;
}

.cta-box h2{
    font-size:50px;
    font-weight:800;
}

.cta-box p{
    margin-top:20px;
    font-size:20px;
    color:#dbeafe;
}

.cta-box .btn{
    margin-top:30px;
    padding:15px 40px;
    border-radius:50px;
    font-weight:600;
}

/* =========================
   FOOTER
========================= */

footer{
    background:#0f172a;
    color:white;
    padding:70px 0 40px;
    margin-top:100px;
}

.footer-title{
    font-size:30px;
    font-weight:700;
}

.footer-text{
    color:#cbd5e1;
    margin-top:20px;
    line-height:1.8;
}

</style>
</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar navbar-expand-lg">

<div class="container">

<a class="navbar-brand">
🏥 ZB-CARE
</a>

<button class="navbar-toggler"
data-bs-toggle="collapse"
data-bs-target="#navMenu">

<span class="navbar-toggler-icon"></span>

</button>

<div class="collapse navbar-collapse" id="navMenu">

<ul class="navbar-nav ms-auto align-items-center">

<li class="nav-item">
<a class="nav-link" href="#">Home</a>
</li>

<li class="nav-item">
<a class="nav-link" href="#">Services</a>
</li>

<li class="nav-item">
<a class="nav-link" href="#">Specialists</a>
</li>

<li class="nav-item ms-3">


</li>

</ul>

</div>

</div>

</nav>

<!-- =========================
     HERO
========================= -->

<section class="hero">

<div class="container">

<div class="hero-content">

<div class="hero-sub">
🏥 Specialist Healthcare & Outpatient Services
</div>

<h1 class="hero-title">

Professional <span>Healthcare</span><br>
For Better Patient Care

</h1>

<p class="hero-text">

ZB-CARE provides outpatient appointment management,
specialist healthcare services, and patient support
for Orthopaedics, Paediatrics, and Dietitian consultation.

</p>

<div class="hero-buttons">

<a href="pages/appointment.php"
class="btn btn-book me-3">

Book Appointment

</a>

<a href="pages/staff_portal.php"
class="btn btn-staff">

Staff Login

</a>

</div>

</div>

</div>

</section>

<!-- =========================
     SEARCH BOX
========================= -->

<div class="container">

<div class="search-box">

<div class="row g-3 align-items-center">

<div class="col-md-5">

<input type="text"
class="form-control"
placeholder="Search specialist or service">

</div>

<div class="col-md-4">

<select class="form-select">

<option>Select Department</option>

<option>Orthopaedics</option>

<option>Paediatrics</option>

<option>Dietitian & Nutrition</option>

</select>

</div>

<div class="col-md-3">

<a href="pages/appointment.php"
class="btn btn-primary w-100 h-100 d-flex align-items-center justify-content-center">

Book Now

</a>

</div>

</div>

</div>

</div>

<!-- =========================
     SERVICES
========================= -->

<section class="section">

<div class="container">

<div class="text-center mb-5">

<h2 class="section-title">
Healthcare Services
</h2>

<p class="section-text">

Professional specialist healthcare services for outpatient consultation and patient care.

</p>

</div>

<div class="row g-4">

<!-- ORTHO -->

<div class="col-md-3">

<div class="service-card">

<div class="service-icon bg-primary text-white">
🦴
</div>

<h4>Orthopaedic Care</h4>

<p class="text-muted mt-3">

Specialist treatment for bone injuries,
joint pain, fractures, and mobility support.

</p>

</div>

</div>

<!-- PAEDS -->

<div class="col-md-3">

<div class="service-card">

<div class="service-icon bg-success text-white">
👶
</div>

<h4>Paediatric Care</h4>

<p class="text-muted mt-3">

Healthcare services for infants,
children, and adolescent patients.

</p>

</div>

</div>

<!-- DIET -->

<div class="col-md-3">

<div class="service-card">

<div class="service-icon bg-danger text-white">
🥗
</div>

<h4>Dietitian Consultation</h4>

<p class="text-muted mt-3">

Nutrition assessment and healthy meal planning
for patient wellbeing.

</p>

</div>

</div>

<!-- HEALTH -->

<div class="col-md-3">

<div class="service-card">

<div class="service-icon bg-warning text-white">
🩺
</div>

<h4>Health Screening</h4>

<p class="text-muted mt-3">

Routine health monitoring and early
medical assessment support.

</p>

</div>

</div>

</div>

</div>

</section>

<!-- =========================
     WHY CHOOSE US
========================= -->

<section class="section bg-white">

<div class="container">

<div class="row align-items-center g-5">

<div class="col-md-6">

<h2 class="section-title">
Why Choose ZB-CARE?
</h2>

<p class="section-text">

ZB-CARE helps patients access specialist healthcare services efficiently through an organized outpatient appointment system.

</p>

<p class="section-text">

Our platform supports faster appointment management,
better patient monitoring, and improved healthcare service delivery.

</p>

<a href="pages/appointment.php"
class="btn btn-primary btn-lg mt-3 px-4">

Book Consultation

</a>

</div>

<div class="col-md-6">

<img src="https://images.unsplash.com/photo-1586773860418-d37222d8fce3?q=80&w=1200&auto=format&fit=crop"
class="info-img">

</div>

</div>

</div>

</section>

<!-- =========================
     SPECIALIST AREA
========================= -->

<section class="section">

<div class="container">

<div class="text-center mb-5">

<h2 class="section-title">
Specialist Care Areas
</h2>

<p class="section-text">

Dedicated healthcare departments focused on specialist outpatient treatment and patient support.

</p>

</div>

<div class="row g-4">

<!-- ORTHO -->

<div class="col-md-4">

<div class="department-card">

<div class="department-icon">
🦴
</div>

<h4>Orthopaedics</h4>

<p class="text-muted">

Bone, joint, spine, and injury treatment support.

</p>

</div>

</div>

<!-- PAEDS -->

<div class="col-md-4">

<div class="department-card">

<div class="department-icon">
👶
</div>

<h4>Paediatrics</h4>

<p class="text-muted">

Healthcare services focused on child wellness and development.

</p>

</div>

</div>

<!-- DIET -->

<div class="col-md-4">

<div class="department-card">

<div class="department-icon">
🥗
</div>

<h4>Dietitian & Nutrition</h4>

<p class="text-muted">

Nutrition planning and dietary consultation for patients.

</p>

</div>

</div>

</div>

</div>

</section>

<!-- =========================
     APPOINTMENT CTA
========================= -->

<section class="section bg-white">

<div class="container">

<div class="cta-box">

<h2>
Need Medical Consultation?
</h2>

<p>

Book your outpatient appointment online with our specialist healthcare team today.

</p>

<a href="pages/appointment.php"
class="btn btn-light">

📅 Book Appointment

</a>

</div>

</div>

</section>

<!-- =========================
     FOOTER
========================= -->

<footer>

<div class="container text-center">

<h2 class="footer-title">
🏥 ZB-CARE Specialist Hospital
</h2>

<p class="footer-text">

Outpatient Appointment & Healthcare Management System
focused on Orthopaedics, Paediatrics, and Dietitian specialist services.

</p>

<p class="mt-4 text-secondary">

© 2026 ZB-CARE. All Rights Reserved.

</p>

</div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>