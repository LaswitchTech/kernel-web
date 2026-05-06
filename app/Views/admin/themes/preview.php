<?php
/**
 * Theme Preview page — Bootstrap component reference for theme development.
 */
?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<h2 class="mb-4">Theme Preview</h2>
<p class="text-muted mb-4">
    This page renders all Bootstrap 5 components so you can review how your theme styles them.
    All data is placeholder/safe — no real content is displayed.
</p>

<!-- Typography -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Typography</h5></div>
    <div class="card-body">
        <h1>Heading 1 — Theme Preview Title</h1>
        <h2>Heading 2 — Section Title</h2>
        <h3>Heading 3 — Subsection Title</h3>
        <h4>Heading 4 — Card Title</h4>
        <h5>Heading 5 — Small Title</h5>
        <h6>Heading 6 — Tiny Title</h6>
        <p class="lead">Lead paragraph — this text stands out with a larger font size for emphasis.</p>
        <hr>
        <blockquote class="blockquote mb-3">
            <p class="mb-0">"A well-designed theme makes every component feel at home."</p>
            <footer class="blockquote-footer">Theme Developer</footer>
        </blockquote>
        <p><code>inline code</code> example within text.</p>
        <pre class="bg-light p-2 rounded">.theme-class {
    color: var(--bs-primary);
}</pre>
        <ul class="list-group mt-3">
            <li class="list-group-item">First list item</li>
            <li class="list-group-item">Second list item</li>
            <li class="list-group-item disabled">Disabled item</li>
            <li class="list-group-item active">Active item</li>
        </ul>
    </div>
</div>

<!-- Buttons -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Buttons</h5></div>
    <div class="card-body">
        <h6 class="mb-2">Contextual buttons</h6>
        <div class="mb-3">
            <button type="button" class="btn btn-primary">Primary</button>
            <button type="button" class="btn btn-secondary">Secondary</button>
            <button type="button" class="btn btn-success">Success</button>
            <button type="button" class="btn btn-danger">Danger</button>
            <button type="button" class="btn btn-warning">Warning</button>
            <button type="button" class="btn btn-info">Info</button>
            <button type="button" class="btn btn-light">Light</button>
            <button type="button" class="btn btn-dark">Dark</button>
        </div>

        <h6 class="mb-2">Outline buttons</h6>
        <div class="mb-3">
            <button type="button" class="btn btn-outline-primary">Primary</button>
            <button type="button" class="btn btn-outline-secondary">Secondary</button>
            <button type="button" class="btn btn-outline-success">Success</button>
            <button type="button" class="btn btn-outline-danger">Danger</button>
            <button type="button" class="btn btn-outline-warning">Warning</button>
            <button type="button" class="btn btn-outline-info">Info</button>
        </div>

        <h6 class="mb-2">Button sizes</h6>
        <div class="mb-3">
            <button type="button" class="btn btn-primary btn-lg">Large button</button>
            <button type="button" class="btn btn-primary">Default button</button>
            <button type="button" class="btn btn-primary btn-sm">Small button</button>
        </div>

        <h6 class="mb-2">Pill buttons</h6>
        <div class="mb-3">
            <button type="button" class="btn btn-primary rounded-pill">Pill button</button>
            <button type="button" class="btn btn-outline-primary rounded-pill">Outline pill</button>
        </div>

        <h6 class="mb-2">Toggle buttons</h6>
        <div class="mb-3">
            <button type="button" class="btn btn-primary active" data-bs-toggle="btn">Active toggle</button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="btn">Toggle me</button>
        </div>

        <h6 class="mb-2">Button groups</h6>
        <div class="btn-group mb-3" role="group" aria-label="Button group">
            <button type="button" class="btn btn-outline-primary">Left</button>
            <button type="button" class="btn btn-outline-primary">Middle</button>
            <button type="button" class="btn btn-outline-primary">Right</button>
        </div>

        <h6 class="mb-2">Button group with checkboxes</h6>
        <div class="btn-group" role="group" aria-label="Basic checkbox toggle button group">
            <input type="checkbox" class="btn-check" id="btncheck1" autocomplete="off">
            <label class="btn btn-outline-primary" for="btncheck1">Checkbox 1</label>

            <input type="checkbox" class="btn-check" id="btncheck2" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="btncheck2">Checkbox 2</label>

            <input type="checkbox" class="btn-check" id="btncheck3" autocomplete="off">
            <label class="btn btn-outline-primary" for="btncheck3">Checkbox 3</label>
        </div>
    </div>
</div>

<!-- Alerts -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Alerts</h5></div>
    <div class="card-body">
        <div class="alert alert-primary" role="alert">
            <i class="bi bi-info-circle me-1"></i> A simple primary alert — check it out!
        </div>
        <div class="alert alert-secondary" role="alert">
            <i class="bi bi-info-circle me-1"></i> A simple secondary alert — check it out!
        </div>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i> A simple success alert — <strong>check it out!</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> A simple danger alert — <strong>check it out!</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i> A simple warning alert — <strong>check it out!</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-1"></i> A simple info alert — <strong>check it out!</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <div class="alert alert-dark" role="alert">
            <i class="bi bi-moon-stars me-1"></i> A simple dark alert — <strong>check it out!</strong>
        </div>
    </div>
</div>

<!-- Badges -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Badges</h5></div>
    <div class="card-body">
        <h6 class="mb-2">Contextual badges</h6>
        <div class="mb-3">
            <span class="badge bg-primary">Primary</span>
            <span class="badge bg-secondary">Secondary</span>
            <span class="badge bg-success">Success</span>
            <span class="badge bg-danger">Danger</span>
            <span class="badge bg-warning text-dark">Warning</span>
            <span class="badge bg-info text-dark">Info</span>
            <span class="badge bg-dark">Dark</span>
        </div>

        <h6 class="mb-2">Pill badges</h6>
        <div class="mb-3">
            <span class="badge rounded-pill bg-primary">Primary</span>
            <span class="badge rounded-pill bg-secondary">Secondary</span>
            <span class="badge rounded-pill bg-success">Success</span>
            <span class="badge rounded-pill bg-danger">Danger</span>
        </div>

        <h6 class="mb-2">Badges with buttons</h6>
        <div class="mb-3">
            <button type="button" class="btn btn-primary me-2">
                Notifications <span class="badge bg-light text-dark">4</span>
            </button>
            <button type="button" class="btn btn-outline-primary">
                Messages <span class="badge bg-primary">12</span>
            </button>
        </div>
    </div>
</div>

<!-- Cards -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Cards</h5></div>
    <div class="card-body">
        <h6 class="mb-2">Card with text</h6>
        <div class="card" style="width: 20rem;">
            <div class="card-body">
                <h5 class="card-title">Card title</h5>
                <h6 class="card-subtitle mb-2 text-muted">Card subtitle</h6>
                <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
                <a href="#" class="card-link">Card link</a>
                <a href="#" class="card-link">Another link</a>
            </div>
        </div>

        <h6 class="mb-2 mt-3">Card grid</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Card 1</h5>
                        <p class="card-text text-muted">Some quick example text.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Card 2</h5>
                        <p class="card-text text-muted">Another card.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Card 3</h5>
                        <p class="card-text text-muted">Third card.</p>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="mb-2 mt-3">Horizontal card</h6>
        <div class="card mb-3">
            <div class="row g-0">
                <div class="col-md-4">
                    <div class="bd-placeholder-img bd-placeholder-img-lg d-flex align-items-center justify-content-center" style="height: 140px; background: var(--bs-secondary-bg);" width="336" height="336" role="img" focusable="false" aria-label="Placeholder: Image"><text x="10%" y="50%" dominant-baseline="middle" text-anchor="middle" class="text-muted">Image</text></div>
                </div>
                <div class="col-md-8">
                    <div class="card-body">
                        <h5 class="card-title">Horizontal card title</h5>
                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content.</p>
                        <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forms -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Forms</h5></div>
    <div class="card-body">
        <form>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="formText" class="form-label">Text input</label>
                    <input type="text" class="form-control" id="formText" placeholder="Jane Doe">
                </div>
                <div class="col-md-6">
                    <label for="formEmail" class="form-label">Email input</label>
                    <input type="email" class="form-control" id="formEmail" placeholder="name@example.com">
                </div>
                <div class="col-md-6">
                    <label for="formPassword" class="form-label">Password</label>
                    <input type="password" class="form-control" id="formPassword" placeholder="Password">
                </div>
                <div class="col-md-6">
                    <label for="formSelect" class="form-label">Select</label>
                    <select class="form-select" id="formSelect">
                        <option selected>Open this select menu</option>
                        <option value="1">One</option>
                        <option value="2">Two</option>
                        <option value="3">Three</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="formTextarea" class="form-label">Textarea</label>
                    <textarea class="form-control" id="formTextarea" rows="3">Example textarea content.</textarea>
                </div>
                <div class="col-md-6">
                    <label for="formFile" class="form-label">File input</label>
                    <input class="form-control" type="file" id="formFile">
                </div>
                <div class="col-md-6">
                    <label for="formRange" class="form-label">Range slider</label>
                    <input type="range" class="form-range" id="formRange" min="0" max="5" step="0.5" value="3">
                </div>
            </div>

            <h6 class="mt-4 mb-3">Checkboxes</h6>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="check1">
                    <label class="form-check-label" for="check1">Default checkbox</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="check2" checked>
                    <label class="form-check-label" for="check2">Checked checkbox</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="check3" disabled>
                    <label class="form-check-label" for="check3">Disabled checkbox</label>
                </div>
            </div>

            <h6 class="mt-4 mb-3">Radio buttons</h6>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="radioGroup" id="radio1" checked>
                    <label class="form-check-label" for="radio1">Default radio</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="radioGroup" id="radio2">
                    <label class="form-check-label" for="radio2">Second radio</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="radioGroup" id="radio3" disabled>
                    <label class="form-check-label" for="radio3">Disabled radio</label>
                </div>
            </div>

            <h6 class="mt-4 mb-3">Form switch</h6>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckDefault">
                <label class="form-check-label" for="flexSwitchCheckDefault">Default switch checkbox input</label>
            </div>

            <h6 class="mt-4 mb-3">Input groups</h6>
            <div class="input-group mb-3">
                <span class="input-group-text">@</span>
                <input type="text" class="form-control" placeholder="Username">
            </div>
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="Amount">
                <span class="input-group-text">.00</span>
            </div>
            <div class="input-group">
                <span class="input-group-text">With textarea</span>
                <textarea class="form-control" rows="2"></textarea>
            </div>
        </form>
    </div>
</div>

<!-- Tables -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Tables</h5>
    </div>
    <div class="card-body p-0">
        <h6 class="mt-3 mb-2 px-3">Striped table</h6>
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Username</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>Marcy</td><td>Atkinson</td><td>@marcyatkin</td></tr>
                <tr><td>2</td><td>Ricky</td><td>Whitley</td><td>@rwhitley</td></tr>
                <tr><td>3</td><td>Becky</td><td>Snow</td><td>@becksnow</td></tr>
                <tr><td>4</td><td>Oscar</td><td>Hayes</td><td>@oscarhayes</td></tr>
                <tr><td>5</td><td>Michael</td><td>Allen</td><td>@mikeallen</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- DataTables -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">DataTables</h5></div>
    <div class="card-body p-0">
        <table id="preview-dt-table" class="table table-hover mb-0 w-100">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Office</th>
                    <th>Age</th>
                    <th>Start date</th>
                    <th>Salary</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Tiger Nixon</td><td>System Architect</td><td>Edinburgh</td><td>61</td><td>2026-01-01</td><td>$320,800</td></tr>
                <tr><td>Garrett Winters</td><td>Accountant</td><td>Tokyo</td><td>63</td><td>2026-01-02</td><td>$170,750</td></tr>
                <tr><td>Ashton Cox</td><td>Junior Technical Author</td><td>San Francisco</td><td>66</td><td>2026-01-03</td><td>$86,000</td></tr>
                <tr><td>Cedric Kelly</td><td>Senior Developer</td><td>Edinburgh</td><td>22</td><td>2026-01-04</td><td>$433,060</td></tr>
                <tr><td>Airi Satou</td><td>Accountant</td><td>Tokyo</td><td>33</td><td>2026-01-05</td><td>$162,700</td></tr>
                <tr><td>Brielle Williamson</td><td>Integration Specialist</td><td>New York</td><td>61</td><td>2026-01-06</td><td>$372,000</td></tr>
                <tr><td>Herrod Chandler</td><td>Sales Assistant</td><td>San Francisco</td><td>59</td><td>2026-01-07</td><td>$137,500</td></tr>
                <tr><td>Rhona Davidson</td><td>Integration Specialist</td><td>Tokyo</td><td>55</td><td>2026-01-08</td><td>$327,900</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Navs & Pagination -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Navs & Pagination</h5></div>
    <div class="card-body">
        <h6 class="mb-2">Nav tabs</h6>
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link active" href="#">Active tab</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">Link</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">Link</a>
            </li>
            <li class="nav-item">
                <a class="nav-link disabled" href="#">Disabled</a>
            </li>
        </ul>

        <h6 class="mb-2">Nav pills</h6>
        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link active" href="#">Active</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">Link</a>
            </li>
            <li class="nav-item">
                <a class="nav-link disabled" href="#">Disabled</a>
            </li>
        </ul>

        <h6 class="mb-2">Vertical nav tabs</h6>
        <div class="d-flex" style="row-gap:1rem;">
            <ul class="nav flex-column nav-pills me-3" style="width: 120px;">
                <li class="nav-item">
                    <a class="nav-link active" href="#">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Settings</a>
                </li>
            </ul>
        </div>

        <h6 class="mb-2 mt-3">Breadcrumb</h6>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item"><a href="#">Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Data</li>
            </ol>
        </nav>

        <h6 class="mb-2 mt-3">Pagination</h6>
        <nav aria-label="Pagination example">
            <ol class="pagination pagination-sm">
                <li class="page-item"><a class="page-link" href="#">1</a></li>
                <li class="page-item active" aria-current="page"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">4</a></li>
                <li class="page-item"><a class="page-link" href="#">5</a></li>
            </ol>
        </nav>
    </div>
</div>

<!-- Accordions -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Accordions</h5></div>
    <div class="card-body">
        <div class="accordion" id="previewAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        Accordion Item #1
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#previewAccordion">
                    <div class="accordion-body">
                        <strong>This is the first item's accordion body.</strong> It is shown by default.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        Accordion Item #2
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#previewAccordion">
                    <div class="accordion-body">
                        <strong>This is the second item's accordion body.</strong> It is hidden by default.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Accordion Item #3
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#previewAccordion">
                    <div class="accordion-body">
                        <strong>This is the third item's accordion body.</strong> It is hidden by default.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Progress -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Progress Bars</h5></div>
    <div class="card-body">
        <h6 class="mb-2">Basic progress</h6>
        <div class="progress mb-3" style="height: 10px;">
            <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <h6 class="mb-2">50% progress</h6>
        <div class="progress mb-3" style="height: 10px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <h6 class="mb-2">Striped progress</h6>
        <div class="progress mb-3" style="height: 20px;">
            <div class="progress-bar progress-bar-striped bg-info" role="progressbar" style="width: 75%;" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <h6 class="mb-2">Animated progress</h6>
        <div class="progress mb-3" style="height: 20px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <h6 class="mb-2">Multi-bar progress</h6>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar bg-primary" role="progressbar" style="width: 15%" aria-valuenow="15" aria-valuemin="0" aria-valuemax="100"></div>
            <div class="progress-bar bg-success" role="progressbar" style="width: 30%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100"></div>
            <div class="progress-bar bg-danger" role="progressbar" style="width: 20%" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Modals</h5></div>
    <div class="card-body">
        <h6 class="mb-2">Basic modal trigger</h6>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#previewModal">
            Launch preview modal
        </button>

        <h6 class="mb-2 mt-3">Scrollable modal trigger</h6>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#previewScrollModal">
            Launch scrollable modal
        </button>

        <!-- Basic Modal -->
        <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modal title</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Modal body text goes here. This is a placeholder preview.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scrollable Modal -->
        <div class="modal fade" id="previewScrollModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Scrollable modal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>This is a scrollable modal. The content below is long enough to demonstrate scrolling behavior.</p>
                        <p><?= str_repeat("Line " . implode(", ", range(1, 20)) . "\n", 1) ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toasts -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Toasts</h5></div>
    <div class="card-body">
        <div class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-info-circle me-1"></i> A simple primary toast — check it out!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div class="toast align-items-center text-white bg-success border-0 mt-2" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle me-1"></i> A success toast.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div class="toast align-items-center text-dark bg-warning border-0 mt-2" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-triangle me-1"></i> A warning toast.
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <a href="/admin" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Administration
    </a>
</div>

<script>
KernelWeb.dt.init('#preview-dt-table', {
    order: [[0, 'asc']],
    pageLength: 5
});
</script>
