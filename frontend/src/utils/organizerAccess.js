export const isDesignatedOrganizer = (account) =>
  account?.role === "organizer"
  && Number(account.department_record?.organizer_user_id) === Number(account.id)
  && Boolean(account.organizer);
