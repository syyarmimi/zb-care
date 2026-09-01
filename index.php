<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>ZB-CARE Specialist Hospital</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

/* =========================================================
   GLOBAL
========================================================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

body{
    background:#f5f8fc;
    font-family:'Segoe UI', Arial, sans-serif;
    overflow-x:hidden;
    color:#0f172a;
}

a{
    text-decoration:none;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar{
    min-height:82px;
    background:rgba(255,255,255,.97);
    padding:15px 0;
    box-shadow:0 2px 18px rgba(15,23,42,.06);
    position:sticky;
    top:0;
    z-index:1000;
    backdrop-filter:blur(14px);
}

.navbar-brand{
    display:flex;
    align-items:center;
    gap:10px;
    color:#1565ea !important;
    font-size:28px;
    font-weight:850;
    letter-spacing:-.5px;
}

.brand-icon{
    width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eff6ff;
    color:#2563eb;
    border-radius:12px;
    font-size:21px;
}

.navbar-nav{
    gap:4px;
}

.nav-link{
    position:relative;
    color:#334155 !important;
    font-size:14px;
    font-weight:600;
    padding:10px 14px !important;
    border-radius:9px;
    transition:.2s ease;
}

.nav-link:hover{
    color:#2563eb !important;
    background:#eff6ff;
}

.walkin-nav{
    display:flex;
    align-items:center;
    gap:6px;
    color:#059669 !important;
}

.walkin-nav:hover{
    color:#047857 !important;
    background:#ecfdf5;
}

.payment-nav{
    display:flex;
    align-items:center;
    gap:6px;
    color:#2563eb !important;
}

.payment-nav:hover{
    background:#eff6ff;
}

.navbar-toggler{
    border:none;
    box-shadow:none !important;
}


/* =========================================================
   HERO
========================================================= */

.hero{
    position:relative;
    min-height:calc(100vh - 82px);
    display:flex;
    align-items:center;

    background:
        linear-gradient(
            90deg,
            rgba(15,23,42,.82) 0%,
            rgba(15,23,42,.72) 40%,
            rgba(15,23,42,.35) 100%
        ),
        url(
            'https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?q=80&w=1800&auto=format&fit=crop'
        );

    background-size:cover;
    background-position:center;
}

.hero::after{
    content:'';
    position:absolute;
    inset:auto 0 0 0;
    height:140px;
    background:linear-gradient(
        transparent,
        rgba(15,23,42,.12)
    );
    pointer-events:none;
}

.hero-content{
    position:relative;
    z-index:2;
    max-width:920px;
    color:white;
}

.hero-sub{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-bottom:24px;
    padding:10px 17px;
    border:1px solid rgba(255,255,255,.17);
    border-radius:50px;
    background:rgba(255,255,255,.13);
    font-size:14px;
    font-weight:600;
    backdrop-filter:blur(7px);
}

.hero-title{
    margin:0;
    max-width:900px;
    color:white;
    font-size:64px;
    line-height:1.12;
    font-weight:850;
    letter-spacing:-1.5px;
}

.hero-title span{
    color:#60a5fa;
}

.hero-text{
    max-width:760px;
    margin-top:26px;
    color:#e2e8f0;
    font-size:18px;
    line-height:1.8;
}

.hero-buttons{
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:35px;
}

.hero-buttons .btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:52px;
    padding:13px 24px;
    border-radius:13px;
    font-size:14px;
    font-weight:700;
    transition:.2s ease;
}

.btn-book{
    background:#0d6efd;
    color:white;
    border:1px solid #0d6efd;
    box-shadow:0 8px 24px rgba(13,110,253,.25);
}

.btn-book:hover{
    background:#0b5ed7;
    border-color:#0b5ed7;
    color:white;
    transform:translateY(-2px);
}

.btn-walkin-hero{
    background:#059669;
    color:white;
    border:1px solid #059669;
    box-shadow:0 8px 24px rgba(5,150,105,.20);
}

.btn-walkin-hero:hover{
    background:#047857;
    border-color:#047857;
    color:white;
    transform:translateY(-2px);
}

.btn-payment-hero{
    border:1px solid rgba(255,255,255,.35);
    background:rgba(255,255,255,.09);
    color:white;
    backdrop-filter:blur(5px);
}

.btn-payment-hero:hover{
    background:white;
    color:#0f172a;
    border-color:white;
    transform:translateY(-2px);
}


/* =========================================================
   GENERAL SECTION
========================================================= */

.section{
    padding:95px 0;
}

.section-light{
    background:#fff;
}

.section-heading{
    max-width:750px;
    margin:0 auto 48px;
    text-align:center;
}

.section-label{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-bottom:12px;
    color:#2563eb;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:1px;
}

.section-title{
    margin:0;
    color:#0f172a;
    font-size:42px;
    font-weight:800;
    letter-spacing:-.7px;
}

.section-text{
    margin-top:15px;
    color:#64748b;
    font-size:16px;
    line-height:1.8;
}


/* =========================================================
   SERVICES
========================================================= */

.service-card{
    height:100%;
    padding:28px;
    border:1px solid #e6edf5;
    border-radius:18px;
    background:white;
    box-shadow:0 6px 24px rgba(15,23,42,.04);
    transition:.22s ease;
}

.service-card:hover{
    transform:translateY(-5px);
    box-shadow:0 14px 35px rgba(15,23,42,.08);
    border-color:#d8e4f2;
}

.service-icon{
    width:58px;
    height:58px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:20px;
    border-radius:14px;
    font-size:24px;
}

.service-blue{
    background:#eff6ff;
    color:#2563eb;
}

.service-green{
    background:#ecfdf5;
    color:#059669;
}

.service-purple{
    background:#f5f3ff;
    color:#7c3aed;
}

.service-red{
    background:#fff1f2;
    color:#e11d48;
}

.service-card h4{
    margin:0;
    color:#0f172a;
    font-size:18px;
    font-weight:750;
}

.service-card p{
    margin-top:10px;
    margin-bottom:0;
    color:#64748b;
    font-size:14px;
    line-height:1.7;
}

.service-link{
    display:inline-flex;
    align-items:center;
    gap:5px;
    margin-top:16px;
    color:#059669;
    font-size:12px;
    font-weight:750;
}

.service-link:hover{
    color:#047857;
}


/* =========================================================
   WALK-IN SECTION
========================================================= */

.walkin-section{
    padding:90px 0;
    background:#f0fdf8;
}

.walkin-wrapper{
    overflow:hidden;
    border:1px solid #d1fae5;
    border-radius:24px;
    background:#fff;
    box-shadow:0 12px 35px rgba(15,23,42,.05);
}

.walkin-content{
    padding:48px;
}

.walkin-label{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-bottom:14px;
    color:#059669;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:1px;
}

.walkin-title{
    margin:0;
    color:#0f172a;
    font-size:36px;
    line-height:1.2;
    font-weight:800;
    letter-spacing:-.6px;
}

.walkin-text{
    max-width:590px;
    margin-top:16px;
    color:#64748b;
    font-size:15px;
    line-height:1.8;
}

.walkin-steps{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
    margin-top:25px;
}

.walkin-step{
    display:flex;
    align-items:center;
    gap:11px;
    padding:13px;
    border:1px solid #ecf0f4;
    border-radius:11px;
    background:#fff;
}

.walkin-step-number{
    width:31px;
    height:31px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 31px;
    border-radius:9px;
    background:#ecfdf5;
    color:#059669;
    font-size:11px;
    font-weight:850;
}

.walkin-step span:last-child{
    color:#475569;
    font-size:12px;
    font-weight:650;
}

.walkin-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-top:25px;
    padding:13px 23px;
    border-radius:11px;
    background:#059669;
    color:#fff;
    font-size:13px;
    font-weight:750;
    transition:.2s ease;
}

.walkin-btn:hover{
    background:#047857;
    color:white;
    transform:translateY(-1px);
}








/* =========================================================
   WHY CHOOSE
========================================================= */

.why-wrapper{
    padding:8px 0;
}

.why-title{
    color:#0f172a;
    font-size:42px;
    font-weight:800;
    letter-spacing:-.7px;
}

.why-text{
    margin-top:18px;
    color:#64748b;
    font-size:16px;
    line-height:1.8;
}

.feature-list{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
    margin-top:25px;
}

.feature-item{
    display:flex;
    align-items:center;
    gap:10px;
    color:#334155;
    font-size:14px;
    font-weight:600;
}

.feature-check{
    width:30px;
    height:30px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 30px;
    border-radius:50%;
    background:#ecfdf5;
    color:#16a34a;
    font-size:13px;
}

.btn-main{
    display:inline-flex;
    align-items:center;
    gap:7px;
    margin-top:28px;
    padding:12px 22px;
    background:#2563eb;
    color:white;
    border-radius:11px;
    font-size:14px;
    font-weight:700;
}

.btn-main:hover{
    background:#1d4ed8;
    color:white;
}

.info-img{
    width:100%;
    height:440px;
    object-fit:cover;
    border-radius:22px;
    box-shadow:0 16px 40px rgba(15,23,42,.12);
}


/* =========================================================
   SPECIALIST
========================================================= */

.department-card{
    height:100%;
    padding:34px 28px;
    text-align:center;
    background:white;
    border:1px solid #e6edf5;
    border-radius:18px;
    box-shadow:0 6px 24px rgba(15,23,42,.04);
    transition:.22s ease;
}

.department-card:hover{
    transform:translateY(-5px);
    box-shadow:0 14px 35px rgba(15,23,42,.08);
}

.department-icon{
    width:72px;
    height:72px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 20px;
    border-radius:18px;
    font-size:31px;
}

.department-ortho{
    background:#eff6ff;
    color:#2563eb;
}

.department-paeds{
    background:#fff7ed;
    color:#ea580c;
}

.department-neuro{
    background:#f5f3ff;
    color:#7c3aed;
}

.department-card h4{
    color:#0f172a;
    font-size:20px;
    font-weight:750;
}

.department-card p{
    margin-top:10px;
    margin-bottom:0;
    color:#64748b;
    font-size:14px;
    line-height:1.7;
}


/* =========================================================
   PAYMENT INFO SECTION
========================================================= */

.payment-section{
    padding:85px 0;
    background:#f8fbff;
}

.payment-card{
    overflow:hidden;
    border:1px solid #dbeafe;
    border-radius:22px;
    background:linear-gradient(135deg,#eff6ff,#ffffff);
    box-shadow:0 12px 35px rgba(37,99,235,.07);
}

.payment-content{
    padding:45px;
}

.payment-icon{
    width:58px;
    height:58px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:22px;
    border-radius:16px;
    background:#2563eb;
    color:white;
    font-size:24px;
}

.payment-title{
    color:#0f172a;
    font-size:32px;
    font-weight:800;
}

.payment-text{
    max-width:580px;
    margin-top:12px;
    color:#64748b;
    font-size:15px;
    line-height:1.8;
}

.payment-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-top:22px;
    padding:12px 22px;
    border-radius:11px;
    background:#2563eb;
    color:white;
    font-size:14px;
    font-weight:700;
}

.payment-btn:hover{
    background:#1d4ed8;
    color:white;
}

.payment-visual{
    height:100%;
    min-height:330px;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px;
    background:#eaf3ff;
}

.bill-preview{
    width:100%;
    max-width:370px;
    padding:25px;
    border-radius:17px;
    background:white;
    box-shadow:0 12px 30px rgba(15,23,42,.10);
}

.bill-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding-bottom:17px;
    border-bottom:1px solid #eef2f7;
}

.bill-brand{
    color:#2563eb;
    font-size:18px;
    font-weight:800;
}

.bill-status{
    padding:5px 9px;
    border-radius:30px;
    background:#fff7ed;
    color:#c2410c;
    font-size:10px;
    font-weight:750;
}

.bill-row{
    display:flex;
    justify-content:space-between;
    gap:20px;
    padding:11px 0;
    color:#64748b;
    font-size:13px;
    border-bottom:1px solid #f1f5f9;
}

.bill-total{
    display:flex;
    justify-content:space-between;
    margin-top:15px;
    color:#0f172a;
    font-size:17px;
    font-weight:800;
}


/* =========================================================
   CTA
========================================================= */

.cta-section{
    padding:95px 0;
    background:white;
}

.cta-box{
    position:relative;
    overflow:hidden;
    padding:58px 30px;
    text-align:center;
    color:white;
    border-radius:24px;
    background:linear-gradient(
        135deg,
        #1d4ed8,
        #2563eb,
        #3b82f6
    );
    box-shadow:0 15px 40px rgba(37,99,235,.20);
}

.cta-box::before{
    content:'';
    position:absolute;
    width:250px;
    height:250px;
    border-radius:50%;
    background:rgba(255,255,255,.08);
    right:-80px;
    top:-90px;
}

.cta-box::after{
    content:'';
    position:absolute;
    width:180px;
    height:180px;
    border-radius:50%;
    background:rgba(255,255,255,.06);
    left:-50px;
    bottom:-80px;
}

.cta-content{
    position:relative;
    z-index:2;
}

.cta-box h2{
    margin:0;
    font-size:38px;
    font-weight:800;
}

.cta-box p{
    max-width:670px;
    margin:15px auto 0;
    color:#dbeafe;
    font-size:16px;
    line-height:1.8;
}

.cta-buttons{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    margin-top:25px;
}

.cta-buttons .btn{
    margin:0;
    padding:12px 25px;
    border-radius:11px;
    font-size:14px;
    font-weight:700;
}

.cta-walkin{
    border:1px solid rgba(255,255,255,.4);
    background:rgba(255,255,255,.12);
    color:white;
}

.cta-walkin:hover{
    background:white;
    color:#0f172a;
}


/* =========================================================
   FOOTER
========================================================= */

footer{
    padding:58px 0 30px;
    background:#0f172a;
    color:white;
}

.footer-brand{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:9px;
    font-size:25px;
    font-weight:800;
}

.footer-text{
    max-width:700px;
    margin:17px auto 0;
    color:#94a3b8;
    font-size:14px;
    line-height:1.8;
}

.footer-links{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:20px;
    flex-wrap:wrap;
    margin-top:25px;
}

.footer-links a{
    color:#cbd5e1;
    font-size:13px;
}

.footer-links a:hover{
    color:white;
}

.footer-divider{
    margin:35px 0 25px;
    border-color:#273449;
    opacity:1;
}

.copyright{
    margin:0;
    color:#64748b;
    font-size:12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:991px){

    .navbar-nav{
        margin-top:15px;
        align-items:flex-start !important;
    }

    .nav-link{
        width:100%;
        margin:2px 0;
    }

    .hero{
        min-height:720px;
        background-position:center;
    }

    .hero-title{
        font-size:48px;
    }

    .hero-text{
        font-size:17px;
    }

    .section{
        padding:75px 0;
    }

    .section-title,
    .why-title{
        font-size:36px;
    }

    .info-img{
        height:380px;
    }

    .walkin-content{
        padding:35px;
    }

    .payment-content{
        padding:35px;
    }
}


@media(max-width:767px){

    .navbar{
        min-height:auto;
    }

    .navbar-brand{
        font-size:23px;
    }

    .brand-icon{
        width:36px;
        height:36px;
    }

    .hero{
        min-height:720px;
    }

    .hero-title{
        font-size:38px;
        letter-spacing:-.6px;
    }

    .hero-text{
        font-size:15px;
    }

    .hero-buttons{
        align-items:stretch;
        flex-direction:column;
    }

    .hero-buttons .btn{
        width:100%;
    }

    .section-title,
    .why-title{
        font-size:31px;
    }

    .section-text{
        font-size:15px;
    }

    .feature-list,
    .walkin-steps{
        grid-template-columns:1fr;
    }

    .info-img{
        height:300px;
    }

    .walkin-content{
        padding:28px;
    }

    .walkin-title{
        font-size:29px;
    }

    .payment-content{
        padding:28px;
    }

    .payment-title{
        font-size:28px;
    }

    .cta-box{
        padding:45px 22px;
    }

    .cta-box h2{
        font-size:30px;
    }

    .cta-buttons{
        flex-direction:column;
        align-items:stretch;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar navbar-expand-lg">

<div class="container">

<a
    href="index.php"
    class="navbar-brand"
>

<span class="brand-icon">
<i class="bi bi-hospital"></i>
</span>

ZB-CARE

</a>


<button
    class="navbar-toggler"
    type="button"
    data-bs-toggle="collapse"
    data-bs-target="#navMenu"
    aria-controls="navMenu"
    aria-expanded="false"
    aria-label="Toggle navigation"
>

<span class="navbar-toggler-icon"></span>

</button>


<div
    class="collapse navbar-collapse"
    id="navMenu"
>

<ul class="navbar-nav ms-auto align-items-center">

<li class="nav-item">

<a class="nav-link" href="#home">
Home
</a>

</li>


<li class="nav-item">

<a class="nav-link" href="#services">
Services
</a>

</li>


<li class="nav-item">

<a class="nav-link" href="#specialists">
Specialists
</a>

</li>


<!-- WALK-IN -->

<li class="nav-item">

<a
    class="nav-link walkin-nav"
    href="pages/walkin_register.php"
>

<i class="bi bi-person-walking"></i>

Walk-In

</a>

</li>


<!-- PAYMENT -->

<li class="nav-item">

<a
    class="nav-link payment-nav"
    href="pages/payment.php"
>

<i class="bi bi-credit-card"></i>

Payment

</a>

</li>

</ul>

</div>

</div>

</nav>


<!-- =========================================================
     HERO
========================================================= -->

<section
    class="hero"
    id="home"
>

<div class="container">

<div class="hero-content">

<div class="hero-sub">

<i class="bi bi-hospital"></i>

Specialist Healthcare & Outpatient Services

</div>


<h1 class="hero-title">

Modern

<span>
Hospital Management
</span>

<br>

For Better Healthcare Delivery

</h1>


<p class="hero-text">

ZB-CARE is an integrated hospital management system supporting
appointment scheduling, walk-in consultations, patient admissions,
ward management, diagnosis recording, medication administration,
and specialist healthcare services.

</p>


<div class="hero-buttons">


<a
    href="pages/appointment.php"
    class="btn btn-book"
>

<i class="bi bi-calendar2-check"></i>

Book Appointment

</a>


<a
    href="pages/walkin_register.php"
    class="btn btn-walkin-hero"
>

<i class="bi bi-person-walking"></i>

Walk-In Registration

</a>


<a
    href="pages/payment.php"
    class="btn btn-payment-hero"
>

<i class="bi bi-credit-card"></i>

Make Payment

</a>


</div>

</div>

</div>

</section>


<!-- =========================================================
     SERVICES
========================================================= -->

<section
    class="section"
    id="services"
>

<div class="container">

<div class="section-heading">

<div class="section-label">

<i class="bi bi-heart-pulse"></i>

Our Services

</div>


<h2 class="section-title">
Healthcare Services
</h2>


<p class="section-text">

Integrated healthcare services designed to support patients
from appointment booking until consultation, admission and medication management.

</p>

</div>


<div class="row g-4">


<!-- APPOINTMENT -->

<div class="col-xl-3 col-md-6">

<div class="service-card">

<div class="service-icon service-blue">

<i class="bi bi-calendar2-check"></i>

</div>

<h4>
Appointment Management
</h4>

<p>
Online appointment booking, doctor scheduling and patient appointment management.
</p>

</div>

</div>


<!-- WALK-IN -->

<div class="col-xl-3 col-md-6">

<div class="service-card">

<div class="service-icon service-green">

<i class="bi bi-person-walking"></i>

</div>

<h4>
Walk-In Consultation
</h4>

<p>
Register online for a walk-in consultation and receive your queue number before consultation.
</p>

<a
    href="pages/walkin_register.php"
    class="service-link"
>

Register Walk-In

<i class="bi bi-arrow-right"></i>

</a>

</div>

</div>


<!-- ADMISSION -->

<div class="col-xl-3 col-md-6">

<div class="service-card">

<div class="service-icon service-purple">

<i class="bi bi-hospital"></i>

</div>

<h4>
Patient Admission
</h4>

<p>
Patient admission, bed allocation, ward monitoring and discharge management.
</p>

</div>

</div>


<!-- MEDICATION -->

<div class="col-xl-3 col-md-6">

<div class="service-card">

<div class="service-icon service-red">

<i class="bi bi-capsule-pill"></i>

</div>

<h4>
Medication Management
</h4>

<p>
Medication prescribing, pharmacy preparation, nurse collection and administration tracking.
</p>

</div>

</div>

</div>

</div>

</section>


<!-- =========================================================
     WALK-IN REGISTRATION
========================================================= -->

<section
    class="walkin-section"
    id="walkin"
>

<div class="container">

<div class="walkin-wrapper">

<div class="row g-0 align-items-stretch">


<div class="col-lg-12">

<div class="walkin-content">

<div class="walkin-label">

Walk-In Registration

</div>


<h2 class="walkin-title">

Need a Consultation Without an Appointment?

</h2>


<p class="walkin-text">

Register for a walk-in consultation through ZB-CARE.
Enter your IC number, select a specialist department and receive
a queue number for your visit.

</p>


<div class="walkin-steps">


<div class="walkin-step">

<span class="walkin-step-number">
1
</span>

<span>
Enter your IC number
</span>

</div>


<div class="walkin-step">

<span class="walkin-step-number">
2
</span>

<span>
Confirm or register patient details
</span>

</div>


<div class="walkin-step">

<span class="walkin-step-number">
3
</span>

<span>
Select specialist department
</span>

</div>


<div class="walkin-step">

<span class="walkin-step-number">
4
</span>

<span>
Receive your queue number
</span>

</div>


</div>


<a
    href="pages/walkin_register.php"
    class="walkin-btn"
>

<i class="bi bi-ticket-perforated"></i>

Register for Walk-In

</a>


</div>

</div>




</div>

</div>

</div>

</section>


<!-- =========================================================
     WHY CHOOSE US
========================================================= -->

<section class="section section-light">

<div class="container">

<div class="row align-items-center g-5">


<div class="col-lg-6">

<div class="why-wrapper">

<div class="section-label">

<i class="bi bi-stars"></i>

About ZB-CARE

</div>


<h2 class="why-title">

Simple Healthcare Management in One Integrated System

</h2>


<p class="why-text">

ZB-CARE helps organize the healthcare journey through an integrated
system connecting appointments, consultations, admissions,
diagnosis and medication management.

</p>


<p class="why-text">

The system supports better coordination between patients,
doctors, nurses and pharmacists while keeping important
healthcare records organized.

</p>


<div class="feature-list">


<div class="feature-item">

<span class="feature-check">
<i class="bi bi-check-lg"></i>
</span>

Appointment Scheduling

</div>


<div class="feature-item">

<span class="feature-check">
<i class="bi bi-check-lg"></i>
</span>

Walk-In Queue Registration

</div>


<div class="feature-item">

<span class="feature-check">
<i class="bi bi-check-lg"></i>
</span>

Patient Admission

</div>


<div class="feature-item">

<span class="feature-check">
<i class="bi bi-check-lg"></i>
</span>

Medication Tracking

</div>


</div>


<a
    href="pages/appointment.php"
    class="btn-main"
>

<i class="bi bi-calendar2-plus"></i>

Book Appointment

</a>


</div>

</div>


<div class="col-lg-6">

<img
    src="https://images.unsplash.com/photo-1586773860418-d37222d8fce3?q=80&w=1200&auto=format&fit=crop"
    class="info-img"
    alt="Hospital healthcare facility"
>

</div>

</div>

</div>

</section>


<!-- =========================================================
     SPECIALIST DEPARTMENTS
========================================================= -->

<section
    class="section"
    id="specialists"
>

<div class="container">

<div class="section-heading">

<div class="section-label">

<i class="bi bi-activity"></i>

Specialist Departments

</div>


<h2 class="section-title">
Specialist Care Areas
</h2>


<p class="section-text">

Specialist healthcare services focused on Orthopaedics,
Paediatrics and Neurology.

</p>

</div>


<div class="row g-4">


<!-- ORTHOPAEDICS -->

<div class="col-lg-4">

<div class="department-card">

<div class="department-icon department-ortho">

<i class="bi bi-bandaid"></i>

</div>

<h4>
Orthopaedics
</h4>

<p>

Specialist care for bone, joint, muscle,
spine and injury-related conditions.

</p>

</div>

</div>


<!-- PAEDIATRICS -->

<div class="col-lg-4">

<div class="department-card">

<div class="department-icon department-paeds">

<i class="bi bi-emoji-smile"></i>

</div>

<h4>
Paediatrics
</h4>

<p>

Specialist healthcare services focused on
children's health, wellness and development.

</p>

</div>

</div>


<!-- NEUROLOGY -->

<div class="col-lg-4">

<div class="department-card">

<div class="department-icon department-neuro">

<i class="bi bi-activity"></i>

</div>

<h4>
Neurology
</h4>

<p>

Specialist care for conditions involving
the brain, nerves and nervous system.

</p>

</div>

</div>


</div>

</div>

</section>


<!-- =========================================================
     PAYMENT
========================================================= -->

<section class="payment-section">

<div class="container">

<div class="payment-card">

<div class="row g-0 align-items-stretch">


<div class="col-lg-7">

<div class="payment-content">

<div class="payment-icon">

<i class="bi bi-credit-card-2-front"></i>

</div>


<h2 class="payment-title">

Hospital Payment Made Simple

</h2>


<p class="payment-text">

Patients can conveniently review outstanding hospital bills
and proceed with payment for consultation, medication
and admission-related charges through ZB-CARE.

</p>


<a
    href="pages/payment.php"
    class="payment-btn"
>

<i class="bi bi-wallet2"></i>

View & Pay Bill

</a>

</div>

</div>


<div class="col-lg-5">

<div class="payment-visual">

<div class="bill-preview">

<div class="bill-header">

<div class="bill-brand">
ZB-CARE
</div>

<span class="bill-status">
UNPAID
</span>

</div>


<div class="bill-row">

<span>
Consultation
</span>

<strong>
RM 50.00
</strong>

</div>


<div class="bill-row">

<span>
Medication
</span>

<strong>
RM 25.00
</strong>

</div>


<div class="bill-row">

<span>
Admission
</span>

<strong>
RM 0.00
</strong>

</div>


<div class="bill-total">

<span>
Total
</span>

<span>
RM 75.00
</span>

</div>

</div>

</div>

</div>


</div>

</div>

</div>

</section>


<!-- =========================================================
     CTA
========================================================= -->

<section class="cta-section">

<div class="container">

<div class="cta-box">

<div class="cta-content">


<h2>
Need Medical Consultation?
</h2>


<p>

Book an outpatient appointment or register for a walk-in consultation
with our Orthopaedics, Paediatrics or Neurology specialist healthcare team.

</p>


<div class="cta-buttons">


<a
    href="pages/appointment.php"
    class="btn btn-light"
>

<i class="bi bi-calendar2-check me-2"></i>

Book Appointment

</a>


<a
    href="pages/walkin_register.php"
    class="btn cta-walkin"
>

<i class="bi bi-person-walking me-2"></i>

Register Walk-In

</a>


</div>

</div>

</div>

</div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

<div class="container text-center">


<div class="footer-brand">

<i class="bi bi-hospital"></i>

ZB-CARE Specialist Hospital

</div>


<p class="footer-text">

Hospital Appointment, Walk-In Consultation, Patient Admission
and Medication Management System supporting specialist services
in Orthopaedics, Paediatrics and Neurology.

</p>


<div class="footer-links">


<a href="#home">
Home
</a>


<a href="#services">
Services
</a>


<a href="#specialists">
Specialists
</a>


<a href="pages/appointment.php">
Appointment
</a>


<a href="pages/walkin_register.php">
Walk-In
</a>


<a href="pages/payment.php">
Payment
</a>


</div>


<hr class="footer-divider">


<p class="copyright">

© 2026 ZB-CARE. All Rights Reserved.

</p>


</div>

</footer>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>