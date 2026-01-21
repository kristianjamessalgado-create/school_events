// Current active section
let currentSection = document.querySelector('section.active');

// Map section IDs to video files
const videos = {
    hero: '/school_events/landing.mp4',
    features: '/school_events/landing1.mp4',
    roles: '/school_events/landing2.mp4',
};

// Section transition function
function goToSection(id){
    const nextSection = document.getElementById(id);
    if(currentSection === nextSection) return;

    // Animate current section out to left
    currentSection.classList.remove('active');
    currentSection.classList.add('exit-left');

    // Animate next section in from right
    nextSection.classList.remove('exit-left');
    nextSection.classList.add('active');

    // Change background video
    const video = document.getElementById('bgVideo');
    video.src = videos[id];
    video.load();
    video.play();

    currentSection = nextSection;
}

// Modal functions
function openLoginModal() {
    document.getElementById('loginModal').style.display = 'block';
}

function openRegisterModal() {
    // Can be same modal or separate later
    openLoginModal();
}

function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('loginModal');
    if (event.target == modal) {
        closeLoginModal();
    }
}
