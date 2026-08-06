# Repository instructions for agents

## Release versioning

- For every releasable runtime-code or production-dependency change, update the
  `version` field in `config/module.ini` in the same pull request.
- Select the next unused semantic version after the latest repository tag. Do
  not reuse or move an existing tag.
- Documentation-only, test-only, and CI-only changes do not require a version
  bump unless the change is intentionally being published as a release.
- When publishing a release, tag the exact merged commit only after CI passes,
  publish matching GitHub Release notes, and verify that the new release is not
  a draft or prerelease.
