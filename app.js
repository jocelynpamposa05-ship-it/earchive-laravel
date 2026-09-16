    // --- Mobile vs. Desktop Detection ---

    // This is a simple way to check if the user is on a mobile device.

    // You can insert your mobile-specific PWA logic inside the `if (isMobile)` block.

    const isMobile = /Mobi|Android|iPhone|iPad|iPod/i.test(window.navigator.userAgent);



    if (isMobile) {

    console.log("📱 Mobile device detected.");



    // This function finds the menu and injects the new items.

    const injectMobileMenu = () => {
        // --- INJECT CSS TO HIDE DESKTOP DUPLICATION ---
        // This hides the original sidebar, header, and navs so they don't show below the new mobile header.
        const mobileStyle = document.createElement('style');
        mobileStyle.textContent = `
            @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'); /* Ensure icons are available */

            /* --- GLOBAL MOBILE OVERRIDES --- */
            /* Hide desktop navigation and adjust body for the new fixed header. */
            .sidebar, .header, .navbar, nav, #main-nav, .top-bar {
                display: none !important;
            }
            body {
                padding-top: 60px !important;
            }
            
            /* --- PAGE-SPECIFIC LAYOUT FIXES --- */
            /* Remove extra spacing on main content containers for a tighter mobile layout. */
            .content, .main-layout, .collection-container {
                padding-top: 15px !important;
                margin-top: 0 !important;
            }


            /* --- CONTACT PAGE STYLES --- */
            /* Style the contact details section for better readability. */
            .contact-page-wrapper {
                padding: 10px !important;
            }
            .contact-section, .contact-card {
                padding: 20px !important; /* Reduce large desktop padding */
            }
            .contact-details {
                display: flex;
                flex-direction: column !important; /* Always stack on mobile */
                gap: 15px !important;
            }
            .contact-details p {
                margin-bottom: 5px !important;
            }

            /* Add icons to contact information paragraphs. */
            .contact-container p, .contact-details p, .contact-info li, .contact-card p {
                position: relative;
                padding-left: 30px;
            }
            
            /* Generic icon styling using ::before pseudo-element */
            .contact-container p::before, .contact-details p::before, .contact-info li::before, .contact-card p::before {
                font-family: 'Font Awesome 6 Free';
                font-weight: 900;
                position: absolute; 
                left: 0; 
                top: 2px; /* Align icon better with text */
                color: #116913;
                font-size: 1.1em;
            }

            /* Specific icons for each piece of information */
            .contact-container p:nth-of-type(1)::before, .contact-details p:nth-of-type(1)::before {
                content: '\\f3c5'; /* fa-map-marker-alt */
            }
            .contact-container p:nth-of-type(2)::before, .contact-details p:nth-of-type(2)::before {
                content: '\\f095'; /* fa-phone */
            }
            .contact-container p:nth-of-type(3)::before, .contact-details p:nth-of-type(3)::before {
                content: '\\f0e0'; /* fa-envelope */
            }

            /* Remove yellow/gold accents on mobile contact page */
            .contact-section {
                border-left: none !important;
            }
            .section-title::after, .card-title::after {
                display: none !important;
            }

            /* Styles for carousel on dashboard page are now in dashboard.php */
            /* --- NEW: Mobile Carousel Styles --- */
            .qa-wrapper { position: relative; padding: 0 35px; }
            .dept-carousel { scrollbar-width: none; /* Firefox */ }
            .dept-carousel::-webkit-scrollbar { display: none; /* Chrome/Safari */ }
            
            .scroll-arrow {
                position: absolute; top: 50%; transform: translateY(-50%);
                background: rgba(255,255,255,0.9); border-radius: 50%; width: 30px; height: 30px;
                display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                color: #116913;
            }
            .arrow-prev { left: 0; }
            .arrow-next { right: 0; }

            .custom-scrollbar-container { width: 80%; margin: 10px auto 0; height: 4px; background-color: #e0e0e0; border-radius: 2px; position: relative; }
            .custom-scrollbar-thumb {
                width: 25%; height: 100%; background-color: #116913; border-radius: 2px;
                position: absolute; left: 0;
            }

            /* --- HORIZONTAL SCROLL FOR QUICK ACCESS --- */
            .quick-access-grid {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                gap: 15px !important;
                padding-bottom: 10px !important;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none; /* Hide scrollbar Firefox */
            }
            .quick-access-grid::-webkit-scrollbar { 
                display: none; /* Hide scrollbar Chrome/Safari */
            }

            .college-card {
                min-width: 140px !important; /* Ensure cards have width to scroll */
                flex: 0 0 auto !important;
            }

            /* --- PROTECTION BANNER --- */
            .protection-banner {
                background-color: #0e6906 !important;
            }
            .protection-banner .back-btn {
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5rem;
            color: #ffffff;
            transition: transform 0.2s ease;
        }

            /* --- FILTER CONTAINER MOBILE OPTIMIZATIONS --- */
            .filter-container {
                position: sticky !important;
                top: 40px !important; /* Adjusted to match 60px header height */
                z-index: 102 !important; /* Ensure it stays above other content */
                background: white !important;
                padding: 5px !important;
                border-radius: 0 0 15px 15px !important;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05) !important;
                display: flex !important;
                flex-wrap: wrap !important; /* Allow wrapping for search bar */
                gap: 5px !important;
                margin-bottom: 15px !important;
                overflow: visible !important; /* Allow dropdowns to pop out without clipping */
            }

            /* Search Box - Full Width on Mobile */
            .filter-container .search-box {
                flex: 0 0 100% !important;
                width: 100% !important;
                margin-bottom: 5px !important;
            }

            /* --- MANUAL WIDTH CONTROL FOR DROPDOWNS --- */
            /* You can edit the percentage below to change the width manually */
            .filter-container select {
                width: 42% !important; 
                flex: 0 0 auto !important; /* Prevents auto-resizing so your width applies */
            }
           

            /* Compact Clear Button (Icon Only) */
            .filter-container .clear-btn {
                font-size: 0 !important; /* Hide text */
                width: 25px !important;
                height: 38px !important;
                padding: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
                border-radius: 5px !important;
                
            }
            
            /* Inject X Icon */
            .filter-container .clear-btn::before {
                content: '\\f00d'; /* FontAwesome fa-times */
                font-family: 'Font Awesome 6 Free';
                font-weight: 900;
                font-size: 16px !important;
                color: white;
                margin-left: 10px !important;
            }
        `;
        document.head.appendChild(mobileStyle);

        // --- MOBILE HEADER BAR ---
        // Create a fixed header container
        const mobileHeader = document.createElement('div');
        mobileHeader.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 60px; background-color: #116913; z-index: 9999; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); box-sizing: border-box;';

        // Left Side: Logo & Title
        const brandDiv = document.createElement('div');
        brandDiv.style.cssText = 'display: flex; align-items: center; gap: 10px; overflow: hidden;';

        const logoImg = document.createElement('img');
        logoImg.src = '/Earchive/img/cpsu_logo.png'; // Absolute path to ensure it loads from anywhere
        logoImg.style.cssText = 'height: 36px; width: auto;';

        const textWrapper = document.createElement('div');
        textWrapper.style.cssText = 'display: flex; flex-direction: column; justify-content: center;';

        const titleSpan = document.createElement('span');
        titleSpan.textContent = 'CENTRAL PHILIPPINE STATE UNIVERSITY';
        titleSpan.style.cssText = 'color: white; font-weight: bold; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'

        const subTitleSpan = document.createElement('span');
        subTitleSpan.textContent = 'E-ARCHIVE';
        subTitleSpan.style.cssText = 'color: white; font-weight: bold; font-size: 11px; line-height: 1.2;';

        brandDiv.appendChild(logoImg);
        textWrapper.appendChild(titleSpan);
        textWrapper.appendChild(subTitleSpan);
        brandDiv.appendChild(textWrapper);
        mobileHeader.appendChild(brandDiv);

        // Right Side: Burger Button
        const burgerBtn = document.createElement('button');
        burgerBtn.innerHTML = '&#9776;'; // Hamburger Character
        burgerBtn.style.cssText = 'background: transparent; color: white; border: none; font-size: 24px; cursor: pointer; padding: 5px; display: flex; align-items: center;';
        
        mobileHeader.appendChild(burgerBtn);
        document.body.appendChild(mobileHeader);
    
        // --- SIDE DRAWER (Right Side) ---
        const drawer = document.createElement('div');
        drawer.style.cssText = 'position: fixed; top: 0; right: -300px; width: 280px; height: 100%; background: #ffffff; z-index: 10000; transition: right 0.3s ease; box-shadow: -2px 0 10px rgba(0,0,0,0.1); overflow-y: auto; display: flex; flex-direction: column; padding-bottom: 20px;';
        
        // Header with Close Button
        const drawerHeader = document.createElement('div');
        drawerHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #116913; color: white; border-bottom: 1px solid #eee;';
        drawerHeader.innerHTML = '<span style="font-weight: bold; font-size: 1.1rem;">Menu</span>';
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'background: none; border: none; color: white; font-size: 28px; cursor: pointer; line-height: 1;';
        closeBtn.onclick = () => { drawer.style.right = '-300px'; };
        drawerHeader.appendChild(closeBtn);
        drawer.appendChild(drawerHeader);

        // Toggle Action
        burgerBtn.onclick = () => {
            drawer.style.right = drawer.style.right === '0px' ? '-300px' : '0px';
        };
        // Menu Items List - Basic Nav
        // Menu Items List
        const menuList = document.createElement('ul');
        menuList.style.cssText = 'list-style: none; padding: 0; margin: 0;';

        // 1. Add "Home" item
        const homeItem = document.createElement('li');
        homeItem.innerHTML = '<a href="/Earchive/students_systems/dashboard.php" style="display: block; padding: 15px 20px; color: #116913; text-decoration: none; border-bottom: 1px solid #f9f9f9; font-weight: bold;">Home</a>';
        menuList.appendChild(homeItem);

        // 2. Add "Collections" item
        const collectionItem = document.createElement('li');
        collectionItem.innerHTML = '<a href="/Earchive/students_systems/collection.php" style="display: block; padding: 15px 20px; color: #116913; text-decoration: none; border-bottom: 1px solid #f9f9f9; font-weight: bold;">Collection</a>';
        menuList.appendChild(collectionItem);

        // 3. Add "Contact Us" item

        const contactItem = document.createElement('li');
        contactItem.innerHTML = '<a href="/Earchive/students_systems/contact.php" style="display: block; padding: 15px 20px; color: #116913; text-decoration: none; border-bottom: 1px solid #f9f9f9; font-weight: bold;">Contact Us</a>';
        menuList.appendChild(contactItem);

        // 4. Add "Install App" item (Hidden by default)

        const installItem = document.createElement('li');
        installItem.className = 'navInstallApp';
        installItem.style.display = 'block';
        installItem.innerHTML = '<a href="#" style="display: block; padding: 15px 20px; color: #116913; text-decoration: none; border-bottom: 1px solid #f9f9f9; font-weight: bold;">Install App</a>';  
        installItem.addEventListener('click', async (e) => {
            e.preventDefault();
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to the install prompt: ${outcome}`);
                deferredPrompt = null;
            }
        });
        menuList.appendChild(installItem);

        // 5. Add "Log Out" item
        const logoutItem = document.createElement('li');
        logoutItem.innerHTML = '<a href="/Earchive/students_systems/logout.php" style="display: block; padding: 15px 20px; color: #d9534f; text-decoration: none; border-bottom: 1px solid #f9f9f9; font-weight: bold;">Log Out</a>';
        menuList.appendChild(logoutItem);

        drawer.appendChild(menuList);
        document.body.appendChild(drawer);

        // --- MOBILE FILTER FUNCTIONALITY ---
        // This function makes the Department and Year dropdowns auto-submit on mobile,
        // mimicking desktop functionality. It's more reliable to query for the
        // container and attach the listener directly.
        const filterContainer = document.querySelector('.filter-container');
        if (filterContainer) {
            // Use event delegation on the filter container itself for reliability.
            filterContainer.addEventListener('change', (event) => {
                // Check if the changed element is a <select> dropdown.
                if (event.target.tagName === 'SELECT') {
                    // Find the closest form and submit it.
                    const form = event.target.closest('form');
                    if (form) form.submit();
                }
            });
        }
    };




    // Run the injection logic once the DOM is ready.

    if (document.readyState === 'loading') {

        document.addEventListener('DOMContentLoaded', injectMobileMenu);

    } else {

        injectMobileMenu();

    }

    }



    // Variable to store the install prompt event

    let deferredPrompt;



    window.addEventListener('beforeinstallprompt', (e) => {

    console.log('👍 beforeinstallprompt fired! App can be installed.');

    // Prevent the mini-infobar from appearing on mobile

    e.preventDefault();

    // Stash the event so it can be triggered later.

    deferredPrompt = e;

    

    // Update UI to notify the user they can install the PWA

    // 1. Show the Sidebar Link (Target all instances via class)

    const installLinks = document.querySelectorAll('.navInstallApp');

    installLinks.forEach(link => {

        link.style.display = 'list-item'; // More appropriate for an <li> element

    });



    // 2. Handle the dedicated Install Page button

    const installActionBtn = document.getElementById('installActionBtn');

    if (installActionBtn) {

        installActionBtn.addEventListener('click', async () => {

        deferredPrompt.prompt(); // Show the browser install prompt

        // Wait for the user to respond to the prompt

        const { outcome } = await deferredPrompt.userChoice;

        console.log(`User response to the install prompt: ${outcome}`);

        // We've used the prompt, and can't use it again, throw it away

        deferredPrompt = null;

        });

    }

    });



    // Listen for successful installation to hide the button

    window.addEventListener('appinstalled', () => {

    console.log('🎉 App installed successfully');

    // Hide all install buttons

    const installLinks = document.querySelectorAll('.navInstallApp');

    installLinks.forEach(link => {

        link.style.display = 'none';

    });

    deferredPrompt = null;

    });



    // --- PROFILE DROPDOWN TOGGLE ---
    function initProfileToggle() {
        const userMenuToggle = document.getElementById('user-menu-toggle');
        const profileDropdown = document.getElementById('profile-dropdown');
        const profileContainer = document.querySelector('.profile-container');

        if (userMenuToggle && profileDropdown) {
            // Toggle dropdown on icon click
            userMenuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Profile icon clicked');
                profileDropdown.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                // Check if click is outside the profile container
                if (profileContainer && !profileContainer.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            });
        } else {
            console.warn('Profile toggle elements not found');
        }
    }

    // Initialize immediately if DOM is ready, or wait for DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initProfileToggle();
            addValidationStyles();
            initInputValidation();
        });
    } else {
        initProfileToggle();
        addValidationStyles();
        initInputValidation();
    }

    function addValidationStyles() {
        if (document.getElementById('input-validation-styles')) return;
        const style = document.createElement('style');
        style.id = 'input-validation-styles';
        style.textContent = `
            .invalid-input {
                border: 2px solid #e63946 !important;
                animation: shake-field 0.3s ease-in-out;
                outline: none !important;
                box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.12);
            }
            .invalid-message {
                color: #e63946;
                font-size: 0.9rem;
                font-weight: 600;
                margin: 0 0 6px;
                display: block;
            }
            @keyframes shake-field {
                0% { transform: translateX(0); }
                25% { transform: translateX(-6px); }
                50% { transform: translateX(6px); }
                75% { transform: translateX(-4px); }
                100% { transform: translateX(0); }
            }
            .invalid-input.invalid-shake {
                animation: shake-field 0.3s ease-in-out;
            }
        `;
        document.head.appendChild(style);
    }

    function initInputValidation() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', handleFormValidation);
            const fields = form.querySelectorAll('input, textarea, select');
            fields.forEach(field => {
                if (!shouldValidateField(field)) return;
                field.addEventListener('input', () => removeFieldError(field));
                field.addEventListener('change', () => removeFieldError(field));
            });
        });
    }

    function handleFormValidation(event) {
        const form = event.target;
        let isValid = true;
        const fields = form.querySelectorAll('input, textarea, select');
        fields.forEach(field => {
            if (!shouldValidateField(field)) return;
            if (isFieldEmpty(field)) {
                isValid = false;
                showFieldError(field, '*Please input this field.');
            } else {
                removeFieldError(field);
            }
        });
        if (!isValid) {
            event.preventDefault();
            event.stopPropagation();
            const firstInvalid = form.querySelector('.invalid-input');
            if (firstInvalid && typeof firstInvalid.focus === 'function') {
                firstInvalid.focus();
            }
        }
    }

    function shouldValidateField(field) {
        if (!field || field.disabled || field.readOnly) return false;
        if (field.tagName === 'INPUT') {
            const type = field.type ? field.type.toLowerCase() : '';
            return !['hidden', 'button', 'submit', 'reset', 'file', 'image', 'checkbox', 'radio'].includes(type);
        }
        if (field.tagName === 'TEXTAREA' || field.tagName === 'SELECT') {
            return true;
        }
        return false;
    }

    function isFieldEmpty(field) {
        if (!field) return true;
        if (field.tagName === 'SELECT') {
            return field.value === '' || field.value === null;
        }
        return String(field.value || '').trim() === '';
    }

    function showFieldError(field, message) {
        if (!field) return;
        removeFieldError(field);
        field.classList.add('invalid-input', 'invalid-shake');
        const errorMessage = document.createElement('span');
        errorMessage.className = 'invalid-message';
        errorMessage.textContent = message;
        const parent = field.parentNode;
        if (parent) {
            if (field.nextSibling) {
                parent.insertBefore(errorMessage, field.nextSibling);
            } else {
                parent.appendChild(errorMessage);
            }
        }
        field.addEventListener('animationend', () => {
            field.classList.remove('invalid-shake');
        }, { once: true });
    }

    function removeFieldError(field) {
        if (!field) return;
        field.classList.remove('invalid-input', 'invalid-shake');
        const previous = field.parentNode.querySelector('.invalid-message');
        if (previous) {
            previous.remove();
        }
    }

    // Register the service worker

    if ('serviceWorker' in navigator) {

    window.addEventListener('load', () => {

        // The service worker should be at the root of the /Earchive/ directory

        // to control all pages within it.

        let swPath = '/Earchive/service-worker.js';

        navigator.serviceWorker.register(swPath)

        .then((registration) => {

            console.log('Service Worker registered with scope:', registration.scope);

        })

        .catch((error) => {

            console.log('Service Worker registration failed:', error);

        });

    });
    }
