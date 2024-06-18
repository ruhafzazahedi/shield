<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.phone2FATitle') ?> <?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-5"><?= lang('Auth.phone2FATitle') ?></h5>

            <p><?= lang('Auth.confirmPhoneAddress') ?></p>

            <?php if (session('error')) : ?>
                <div class="alert alert-danger"><?= session('error') ?></div>
            <?php endif ?>

            <form action="<?= url_to('auth-action-handle') ?>" method="post">
                <?= csrf_field() ?>

                <!-- Phone -->
                <div class="mb-2">
                    <input type="text" class="form-control" name="phone"
                        inputmode="text" autocomplete="phone" placeholder="<?= lang('Auth.phone') ?>"
                        <?php /** @var CodeIgniter\Shield\Entities\User $user */ ?>
                        value="<?= old('phone', $user->phone) ?>" required>
                </div>

                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block"><?= lang('Auth.send') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
