<header class="header">
    <div class="logo-section">
        <img src="/Earchive/img/cpsu_logo.png" alt="CPSU Logo">
        <div class="header-text">
            <h1>CENTRAL PHILIPPINES STATE UNIVERSITY</h1>
            <h1>E-ARCHIVE</h1>
        </div>
    </div>
    <div class="profile-container">
        <div class="user-profile-icon" id="user-menu-toggle">
            <i class="fa-solid fa-circle-user"></i>
        </div>
        <div class="profile-dropdown" id="profile-dropdown">
            <div class="dropdown-header">
                <div class="avatar"><i class="fa-solid fa-circle-user"></i></div>
                <div class="user-info">
                    <h4 style="text-transform: capitalize;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></h4>
                    <p class="email"><?php echo htmlspecialchars($_SESSION['email'] ?? 'admin@email.com'); ?></p>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* Admin header font style matching the student header */
.header-text h1 {
    font-size: 1.1rem;
    margin: 0;
    color: #ffffff;
    text-transform: uppercase;
    font-family: 'Inter', sans-serif;
    font-weight: 700;
    letter-spacing: 0.05em;
    line-height: 1.1;
}

}

.user-profile-icon {
    cursor: pointer;
    font-size: 2.2rem; /* Slightly larger icon */
    color: #ffffff;
    transition: color 0.3s ease;
}

.user-profile-icon:hover {
    color: #ebe80f; /* Highlight color on hover */
}

.profile-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 55px; /* Position below the header */
    background: white;
    border-radius: 10px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.1);
    width: 300px;
    max-width: 90vw;
    z-index: 1000;
    overflow: hidden;
    border: 1px solid #eee;
    animation: fadeIn 0.2s ease-out;
}

.profile-dropdown.show {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.dropdown-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px;
    background: #f8f9fa;
    text-align: center;
}

.dropdown-header .avatar { font-size: 3.5rem; color: #116913; }
.dropdown-header .user-info h4 { margin: 0; font-size: 1.1rem; color: #333; font-weight: 600; }
.dropdown-header .user-info .email { margin: 0; font-size: 0.9rem; color: #6c757d; }
</style>