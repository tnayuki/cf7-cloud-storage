var cf7_cloud_storage_ajax_nonce = 0;

function cf7_cloud_storage_dropzone(name, dropzone, accepts) {
    var isUploading = false;

    var uploadFile = function() {
        if (file.files.length > 1) {
            return;
        }

        if (accepts && Array.isArray(accepts)) {
            var accepted = false;
            for (var extensionOrType of accepts) {
                if (extensionOrType.startsWith('.')) {
                    accepted |= file.files[0].name.endsWith(extensionOrType);
                } else {
                    accepted |= file.files[0].type == extensionOrType;
                }
            }

            if (!accepted) {
                message.classList.add('cf7-cloud-storage-dropzone-message-error');
                message.innerText = _cf7CloudStorageL10n.notAccepted;

                hidden.value = '';

                return;
            }
        }

        isUploading = true;

        message.hidden = true;
        progress.hidden = false;

        var formData = new FormData();
        formData.append('_wpnonce', cf7_cloud_storage_ajax_nonce++);
        formData.append('action', 'get_signed_url');
        formData.append('name', file.files[0].name);
        formData.append('type', file.files[0].type);

        var request = new XMLHttpRequest();
        request.open('POST', cf7_cloud_storage_ajax_url, true);
        request.onreadystatechange = function() {
            if (request.readyState === 4) {
                if (request.status === 200) {
                    var uploadUrl = new URL(request.responseText);

                    var uploadRequest = new XMLHttpRequest();

                    uploadRequest.open('PUT', uploadUrl, true);
                    uploadRequest.setRequestHeader('Content-Type', file.files[0].type);

                    uploadRequest.upload.onprogress = function(e) {
                        progress.value = e.loaded / e.total;
                    };

                    uploadRequest.onreadystatechange = function() {
                        if (uploadRequest.readyState === 4) {
                            isUploading = false;

                            message.hidden = false;
                            progress.hidden = true;

                            if (uploadRequest.status === 200) {
                                message.classList.add('cf7-cloud-storage-dropzone-message-success');
                                message.innerText = file.files[0].name;

                                hidden.value = decodeURIComponent(uploadUrl.pathname.replace(/^\/[\w-]+\//, ''));
                            }
                        }
                    }

                    uploadRequest.onerror = function() {
                        isUploading = false;

                        message.hidden = false;
                        progress.hidden = true;
                    };

                    uploadRequest.send(file.files[0]);
                } else {
                    isUploading = false;

                    message.hidden = false;
                    progress.hidden = true;
                }
            }
        };
        request.onerror = function() {
            isUploading = false;

            message.hidden = false;
            progress.hidden = true;
        };
        request.send(formData);
    };

    var hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = name;
    dropzone.appendChild(hidden);

    var file = document.createElement("input");
    file.type = "file";
    file.hidden = true;
    dropzone.appendChild(file);

    var message = document.createElement('div');
    message.innerText = _cf7CloudStorageL10n.dropFile;
    dropzone.appendChild(message);

    var progress = document.createElement("progress");
    progress.hidden = true;
    progress.className = "cf7-cloud-storage-dropzone-progress"
    dropzone.appendChild(progress);

    file.addEventListener('change', uploadFile); 

    dropzone.addEventListener('click', function (e) {
        if (isUploading) {
            return;
        }

        file.click();
    });

    dropzone.addEventListener('dragleave', function (e) {
        dropzone.classList.remove('cf7-cloud-storage-dropzone-drag-hover');
    });

    dropzone.addEventListener('dragover', function (e) {
        if (!e.dataTransfer.types.includes("Files")) {
            return;
        }

        e.stopPropagation();
        e.preventDefault();

        if (isUploading) {
            return;
        }

        dropzone.classList.add('cf7-cloud-storage-dropzone-drag-hover');
    });

    dropzone.addEventListener('drop', function (e) {
        if (!e.dataTransfer.types.includes("Files")) {
            return;
        }

        e.stopPropagation();
        e.preventDefault();

        if (isUploading) {
            return;
        }

        dropzone.classList.remove('cf7-cloud-storage-dropzone-drag-hover');

        file.files = e.dataTransfer.files;

        uploadFile();
    });
}
