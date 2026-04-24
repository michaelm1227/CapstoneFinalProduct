function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(function(form) {
        form.classList.remove("active");
    });
    const formToShow = document.getElementById(formId);
    if (formToShow) {
        formToShow.classList.add("active");
    } else {
        console.warn(`No form found with ID: ${formId}`);
    }
}

function toggleResumeUpload() {
    const role = document.getElementById("role").value;
    const resumeSection = document.getElementById("resume-section");
    resumeSection.style.display = (role === "employee") ? "block" : "none";
}

function handleRegister(event) {
    const role = document.getElementById("role").value;
    if (role === "employer") {
        event.preventDefault();
        showForm("job-post-form");
    }
}
