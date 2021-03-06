<?php

final class DifferentialInlineCommentEditController
  extends PhabricatorInlineCommentController {

  private $revisionID;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  protected function createComment() {

    // Verify revision and changeset correspond to actual objects.
    $revision_id = $this->revisionID;
    $changeset_id = $this->getChangesetID();

    $viewer = $this->getRequest()->getUser();
    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($revision_id))
      ->executeOne();

    if (!$revision) {
      throw new Exception('Invalid revision ID!');
    }

    if (!id(new DifferentialChangeset())->load($changeset_id)) {
      throw new Exception('Invalid changeset ID!');
    }

    return id(new DifferentialInlineComment())
      ->setRevision($revision)
      ->setChangesetID($changeset_id);
  }

  protected function loadComment($id) {
    return id(new DifferentialInlineCommentQuery())
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function loadCommentByPHID($phid) {
    return id(new DifferentialInlineCommentQuery())
      ->withPHIDs(array($phid))
      ->executeOne();
  }

  protected function loadCommentForEdit($id) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $inline = $this->loadComment($id);
    if (!$this->canEditInlineComment($user, $inline)) {
      throw new Exception('That comment is not editable!');
    }
    return $inline;
  }

  private function canEditInlineComment(
    PhabricatorUser $user,
    DifferentialInlineComment $inline) {

    // Only the author may edit a comment.
    if ($inline->getAuthorPHID() != $user->getPHID()) {
      return false;
    }

    // Saved comments may not be edited, for now, although the schema now
    // supports it.
    if (!$inline->isDraft()) {
      return false;
    }

    // Inline must be attached to the active revision.
    if ($inline->getRevisionID() != $this->revisionID) {
      return false;
    }

    return true;
  }

  protected function deleteComment(PhabricatorInlineCommentInterface $inline) {
    $inline->openTransaction();
      DifferentialDraft::deleteHasDraft(
        $inline->getAuthorPHID(),
        $inline->getRevisionPHID(),
        $inline->getPHID());
      $inline->delete();
    $inline->saveTransaction();
  }

  protected function saveComment(PhabricatorInlineCommentInterface $inline) {
    $inline->openTransaction();
      $inline->save();
      DifferentialDraft::markHasDraft(
        $inline->getAuthorPHID(),
        $inline->getRevisionPHID(),
        $inline->getPHID());
    $inline->saveTransaction();
  }
}
