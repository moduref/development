-- Run once only if moduref_demo uses the previous demo schema.
-- Review duplicate users.name values before adding the unique username key.
USE moduref_demo;

ALTER TABLE users
  CHANGE COLUMN id uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  CHANGE COLUMN name username VARCHAR(120) NOT NULL,
  ADD COLUMN referral_poin INT UNSIGNED NOT NULL DEFAULT 0 AFTER username,
  ADD COLUMN referral_attribution VARCHAR(120) NULL AFTER referral_poin,
  ADD UNIQUE KEY users_username_unique (username);

UPDATE users AS invitee
JOIN referral_attributions AS old_referrals
  ON old_referrals.referred_user_uuid = invitee.uuid
JOIN users AS inviter
  ON inviter.uuid = old_referrals.referrer_user_uuid
SET invitee.referral_attribution = inviter.username;

UPDATE users AS inviter
LEFT JOIN (
  SELECT referrer_user_uuid, COUNT(*) AS referral_total
  FROM referral_attributions
  GROUP BY referrer_user_uuid
) AS old_counts
  ON old_counts.referrer_user_uuid = inviter.uuid
SET inviter.referral_poin = COALESCE(old_counts.referral_total, 0);
