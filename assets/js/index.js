// ===============================
// SECTION NAVIGATION
// ===============================
let currentSection = document.querySelector('section.active');

function goToSection(id) {
    const nextSection = document.getElementById(id);
    if (!nextSection) return;
    if (currentSection === nextSection) return;

    // Exit current section
    if (currentSection) {
        currentSection.classList.remove('active');
        currentSection.classList.add('exit-left');
    }

    // Prepare next section
    nextSection.classList.remove('exit-left');
    nextSection.classList.add('enter-left');

    // Force browser reflow
    nextSection.offsetHeight;

    // Activate next
    nextSection.classList.remove('enter-left');
    nextSection.classList.add('active');

    currentSection = nextSection;

    // Smooth scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ===============================
// MODAL LOGIC
// ===============================
function openLoginModal() {
    document.getElementById('loginModal').style.display = 'flex';
}

function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
}

window.onclick = function(e) {
    const modal = document.getElementById('loginModal');
    if (e.target === modal) closeLoginModal();
}

// ===============================
// SPLINE EYES FOLLOW MOUSE
// ===============================
const spline = document.querySelector('spline-viewer');

if (spline) {
    spline.addEventListener('load', async (e) => {
        try {
            // Wait for the scene to fully initialize
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            const app = e.target;
            
            // Try multiple ways to access the Spline scene
            let scene = null;
            let eyes = null;
            
            // Method 1: Try app.spline
            if (app.spline) {
                scene = app.spline;
            }
            // Method 2: Try app.application
            else if (app.application && app.application.scene) {
                scene = app.application.scene;
            }
            // Method 3: Try accessing through the event detail
            else if (e.detail && e.detail.scene) {
                scene = e.detail.scene;
            }
            
            if (!scene) {
                console.warn("Spline scene not found. Trying alternative access...");
                await new Promise(resolve => setTimeout(resolve, 500));
                if (spline.application) {
                    scene = spline.application.scene;
                }
            }
            
            if (!scene) {
                console.warn("Could not access Spline scene. Eye tracking disabled.");
                return;
            }

            // Try different possible names for the eyes object
            const possibleNames = ['Eyes', 'eyes', 'Eye', 'eye', 'EyesGroup', 'eyesGroup', 'Eyes_Group', 'EyesGroup1'];
            
            for (const name of possibleNames) {
                try {
                    if (scene.findObjectByName) {
                        eyes = scene.findObjectByName(name);
                    } else if (scene.getObjectByName) {
                        eyes = scene.getObjectByName(name);
                    }
                    if (eyes) {
                        console.log(`✓ Found eyes object: ${name}`);
                        break;
                    }
                } catch (err) {
                    // Continue searching
                }
            }

            // If not found by name, try recursive search
            if (!eyes) {
                const searchForEyes = (obj, depth = 0) => {
                    if (depth > 10 || !obj) return null;
                    
                    try {
                        if (obj.name && (obj.name.toLowerCase().includes('eye') || obj.name.toLowerCase().includes('pupil'))) {
                            return obj;
                        }
                        
                        if (obj.children && obj.children.length > 0) {
                            for (const child of obj.children) {
                                const found = searchForEyes(child, depth + 1);
                                if (found) return found;
                            }
                        }
                    } catch (err) {
                        // Skip objects that can't be accessed
                    }
                    return null;
                };
                
                eyes = searchForEyes(scene);
                if (eyes) {
                    console.log(`✓ Found eyes object recursively: ${eyes.name}`);
                }
            }

            if (!eyes) {
                console.warn("⚠ Could not find eyes object. Please check the object name in Spline.");
                return;
            }

            // Mouse tracking with smooth movement
            let targetRotationX = 0;
            let targetRotationY = 0;
            let currentRotationX = 0;
            let currentRotationY = 0;

            const handleMouseMove = (e) => {
                const x = (e.clientX / window.innerWidth - 0.5) * 2;
                const y = (0.5 - e.clientY / window.innerHeight) * 2;

                targetRotationY = x * 0.4;
                targetRotationX = y * 0.4;
            };

            document.addEventListener('mousemove', handleMouseMove);

            // Smooth animation loop
            function animateEyes() {
                currentRotationX += (targetRotationX - currentRotationX) * 0.15;
                currentRotationY += (targetRotationY - currentRotationY) * 0.15;

                try {
                    if (eyes) {
                        if (eyes.rotation !== undefined) {
                            eyes.rotation.y = currentRotationY;
                            eyes.rotation.x = currentRotationX;
                        } else if (eyes.rotationY !== undefined) {
                            eyes.rotationY = currentRotationY;
                            eyes.rotationX = currentRotationX;
                        }
                    }
                } catch (err) {
                    console.warn("Error updating eye rotation:", err);
                }

                requestAnimationFrame(animateEyes);
            }

            animateEyes();
            console.log("✓ Eye tracking initialized successfully");

        } catch (error) {
            console.error("✗ Error initializing eye tracking:", error);
        }
    });
}
