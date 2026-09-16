/* ==========================================================================
   DOCUMENT MANAGEMENT
   ========================================================================== */

document.addEventListener('DOMContentLoaded', async () => {
  if (!(await initShell('documents'))) return;

  await renderDocumentsTable();

  document.getElementById('uploadBtn').addEventListener('click', openUploadModal);
  document.getElementById('uploadForm').addEventListener('submit', handleUploadSubmit);
  document.getElementById('moduleFilter').addEventListener('change', renderDocumentsTable);
});

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

async function renderDocumentsTable() {
  const filterModule = document.getElementById('moduleFilter').value || null;
  const documents = await getDocuments(filterModule);
  const tbody = document.querySelector('#documentsTable tbody');

  if (!documents.length) {
    tbody.innerHTML = '<tr><td colspan="6">' + emptyState('No documents uploaded yet.') + '</td></tr>';
  } else {
    tbody.innerHTML = documents.map(d =>
      '<tr><td><a href="' + d.url + '" target="_blank">' + escapeHtml(d.filename) + '</a></td>' +
      '<td>' + escapeHtml(d.sourceModule) + '</td>' +
      '<td class="amount">' + formatFileSize(d.fileSize) + '</td>' +
      '<td>' + escapeHtml(d.uploadedBy || '\u2014') + '</td>' +
      '<td>' + formatDate(d.uploadedAt) + '</td>' +
      '<td><button class="btn btn-ghost btn-sm" onclick="handleDeleteDocument(' + d.id + ')">Delete</button></td></tr>'
    ).join('');
  }
  document.getElementById('documentsCount').textContent = documents.length + ' document' + (documents.length === 1 ? '' : 's');
}

function openUploadModal() {
  const form = document.getElementById('uploadForm');
  form.reset();
  showFormErrors(form, []);
  openModal('uploadModal');
}

async function handleUploadSubmit(e) {
  e.preventDefault();
  const form = e.target;

  const fileInput = document.getElementById('uploadFile');
  const file = fileInput.files[0];
  const module = document.getElementById('uploadModule').value;

  if (!file) {
    showFormErrors(form, ['Please select a file.']);
    return;
  }

  try {
    await uploadDocument(file, module, null);
    toast('Document uploaded successfully.', 'success');
    closeModal('uploadModal');
    await renderDocumentsTable();
  } catch (err) {
    showFormErrors(form, [err.message]);
  }
}

async function handleDeleteDocument(id) {
  if (!confirm('Delete this document? This cannot be undone.')) return;
  try {
    await deleteDocument(id);
    toast('Document deleted.', 'success');
    await renderDocumentsTable();
  } catch (err) {
    toast(err.message, 'danger');
  }
}