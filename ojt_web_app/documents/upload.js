document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let form = e.target;
    let data = new FormData(form);
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload_documents.php');

    document.getElementById('spinner').style.display = 'block';

    xhr.upload.addEventListener('progress', function(e) {
        let percent = (e.loaded / e.total) * 100;
        let progressBar = document.querySelector('#progressBar div');
        progressBar.style.width = percent + '%';
        progressBar.textContent = Math.round(percent) + '%';
    });

    xhr.onload = function() {
        document.getElementById('spinner').style.display = 'none';
        let response = JSON.parse(xhr.responseText);
        document.getElementById('status').textContent = response.message;
        if (response.status === 'success') {
            document.querySelector('#progressBar div').style.background = '#4caf50';
        } else {
            document.querySelector('#progressBar div').style.background = '#f44336';
        }
    };

    xhr.send(data);
});

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let formData = new FormData(this);
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload_documents.php', true);

    xhr.onload = function() {
        if (xhr.status === 200) {
            let res = JSON.parse(xhr.responseText);
            document.getElementById('status').innerText = res.message;
        } else {
            document.getElementById('status').innerText = 'Upload failed.';
        }
    };

    xhr.send(formData);
});